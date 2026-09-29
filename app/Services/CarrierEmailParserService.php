<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CarrierReleaseData;

final class CarrierEmailParserService
{
    /**
     * Parse raw RFC822/EML message content into structured CarrierReleaseData.
     */
    public function parseRawEmail(string $rawMessage): ?CarrierReleaseData
    {
        [$headers, $body] = $this->splitHeadersAndBody($rawMessage);

        $subject = $this->extractHeader($headers, 'Subject');
        $decodedSubject = $this->decodeMimeHeader($subject);

        $contentType = $this->extractHeader($headers, 'Content-Type');
        $contentTransferEncoding = $this->extractHeader($headers, 'Content-Transfer-Encoding');
        $plainBody = $this->extractTextBody($body, $contentType, $contentTransferEncoding);

        $carrier = $this->detectCarrier($decodedSubject, $plainBody);

        return match ($carrier) {
            'sallaum' => $this->parseSallaum($decodedSubject, $plainBody),
            'grimaldi_acl' => $this->parseGrimaldiAcl($decodedSubject, $plainBody),
            default => null,
        };
    }

    /**
     * Detect carrier based on subject and email content.
     */
    public function detectCarrier(string $subject, string $body): string
    {
        $combined = $subject."\n".$body;

        if (
            str_contains($combined, '*** TELEX RELEASE ***')
            || str_contains(strtoupper($combined), 'SALLAUM LINES')
            || str_contains($subject, 'sallaumlines')
        ) {
            return 'sallaum';
        }

        if (
            str_contains($combined, '*** SEAWAY BILL ***')
            || str_contains(strtoupper($combined), 'GRIMALDI')
            || str_contains(strtoupper($combined), 'ACL')
            || str_contains($subject, 'aclcargo')
        ) {
            return 'grimaldi_acl';
        }

        return 'unknown';
    }

    /**
     * Parse Sallaum Lines Telex Release email.
     */
    public function parseSallaum(string $subject, string $body): ?CarrierReleaseData
    {
        // 1. Extract VIN (17 characters alphanumeric excluding I, O, Q)
        $vin = null;
        if (preg_match('/-\s*Vin:\s*([A-HJ-NPR-Z0-9]{17})/i', $body, $matches)) {
            $vin = strtoupper(trim($matches[1]));
        } elseif (preg_match('/Vin:\s*([A-HJ-NPR-Z0-9]{17})/i', $body, $matches)) {
            $vin = strtoupper(trim($matches[1]));
        } elseif (preg_match('/[A-HJ-NPR-Z0-9]{17}/i', $subject, $matches)) {
            $vin = strtoupper(trim($matches[0]));
        }

        if (! $vin) {
            return null;
        }

        // 2. Extract BL Number
        $blNumber = null;
        if (preg_match('/BL\s*No:\s*([A-Z0-9]+)/i', $body, $matches)) {
            $blNumber = trim($matches[1]);
        } elseif (preg_match('/\b(US\d{7,10})\b/i', $subject, $matches)) {
            $blNumber = trim($matches[1]);
        }

        // 3. Extract Vessel and Voyage
        $vessel = null;
        if (preg_match('/Vessel:\s*(.+)/i', $body, $matches)) {
            $vessel = trim($matches[1]);
        }

        $voyage = null;
        if (preg_match('/Voyage:\s*(.+)/i', $body, $matches)) {
            $voyage = trim($matches[1]);
        }

        // 4. Extract Ports
        $pol = null;
        if (preg_match('/POL:\s*(.+)/i', $body, $matches)) {
            $pol = trim($matches[1]);
        }

        $pod = null;
        if (preg_match('/POD:\s*(.+)/i', $body, $matches)) {
            $pod = trim($matches[1]);
        }

        // 5. Extract Consignee
        $consignee = null;
        if (preg_match('/Consignee:\s*(.+)/i', $body, $matches)) {
            $consignee = trim($matches[1]);
        }

        // 6. Extract Verbatim Release Text Block
        $releaseText = '';
        if (preg_match('/^\s*(\*\*\*\s*TELEX RELEASE\s*\*\*\*[\s\S]+?ID:\s*\d+)/mi', $body, $matches)) {
            $releaseText = trim($matches[1]);
        } elseif (preg_match('/(\*\*\*\s*TELEX RELEASE\s*\*\*\*[\s\S]+?ID:\s*\d+)/i', $body, $matches)) {
            $releaseText = trim($matches[1]);
        } else {
            // Fallback: build canonical representation
            $releaseText = "*** TELEX RELEASE ***\n\n"
                .($vessel ? "Vessel: {$vessel}\n" : '')
                .($voyage ? "Voyage: {$voyage}\n" : '')
                .($pol ? "POL: {$pol}\n" : '')
                .($pod ? "POD: {$pod}\n" : '')
                .($blNumber ? "BL No: {$blNumber}\n" : '')
                ."- Vin: {$vin}\n"
                .($consignee ? "Consignee: {$consignee}\n\n" : "\n")
                ."Please release this shipment to receiver without presentation of the Original Bills of lading, but against proper identification only.\n"
                ."All local charges are for the receiver's account.";
        }

        // Clean up excessive empty lines and quoted-printable artifacts
        $releaseText = $this->cleanQuotedPrintable($releaseText);
        $releaseText = preg_replace("/\n{3,}/", "\n\n", $releaseText) ?? $releaseText;

        return new CarrierReleaseData(
            carrier: 'sallaum',
            releaseType: 'telex_release',
            vin: $vin,
            blNumber: $blNumber,
            pinNumber: null,
            vessel: $vessel,
            voyage: $voyage,
            pol: $pol,
            pod: $pod,
            consignee: $consignee,
            officialReleaseText: $releaseText,
            rawSubject: $subject,
        );
    }

    /**
     * Parse Grimaldi / ACL Seaway Bill email.
     */
    public function parseGrimaldiAcl(string $subject, string $body): ?CarrierReleaseData
    {
        // 1. Extract VIN
        $vin = null;
        if (preg_match('/VIN(?:\/Container)?:\s*([A-HJ-NPR-Z0-9]{17})/i', $body, $matches)) {
            $vin = strtoupper(trim($matches[1]));
        } elseif (preg_match('/[A-HJ-NPR-Z0-9]{17}/i', $subject, $matches)) {
            $vin = strtoupper(trim($matches[0]));
        }

        if (! $vin) {
            return null;
        }

        // 2. Extract BL / Shipment Number
        $blNumber = null;
        if (preg_match('/BL\/Shipment\s*No:\s*([A-Z0-9\-]+)/i', $body, $matches)) {
            $blNumber = trim($matches[1]);
        } elseif (preg_match('/\b(S3-[A-Z0-9]+)\b/i', $subject, $matches)) {
            $blNumber = trim($matches[1]);
        }

        // 3. Extract Pin Number
        $pinNumber = null;
        if (preg_match('/Pin\s*Number:\s*([A-Z0-9]+)/i', $body, $matches)) {
            $pinNumber = trim($matches[1]);
        }

        // 4. Extract Vessel & Voyage
        $vessel = null;
        $voyage = null;
        if (preg_match('/Vessel\/Voyage:\s*(.+)/i', $body, $matches)) {
            $vesselVoyage = trim($matches[1]);
            if (str_contains($vesselVoyage, '/')) {
                [$vessel, $voyage] = explode('/', $vesselVoyage, 2);
                $vessel = trim($vessel);
                $voyage = trim($voyage);
            } else {
                $vessel = $vesselVoyage;
            }
        }

        // 5. Extract Ports
        $pol = null;
        if (preg_match('/Port of Load:\s*(.+)/i', $body, $matches)) {
            $pol = trim($matches[1]);
        }

        $pod = null;
        if (preg_match('/Port of Discharge:\s*(.+)/i', $body, $matches)) {
            $pod = trim($matches[1]);
        }

        // 6. Extract Consignee
        $consignee = null;
        if (preg_match('/Consignee:\s*([\s\S]+?)(?=Clearing Agent:|Pin Number:)/i', $body, $matches)) {
            $consignee = trim(preg_replace('/\s+/', ' ', $matches[1]) ?? $matches[1]);
        }

        // 7. Extract Verbatim Release Text Block
        $releaseText = '';
        if (preg_match('/(?:^|\n)\s*(\*\*\*\s*SEAWAY BILL\s*\*\*\*(?!\s*-\s*[A-Z0-9])[\s\S]+?(?:Notification Rule ID:[^\r\n]*|cargo will not be released[^\r\n]*))/i', $body, $matches)) {
            $releaseText = trim($matches[1]);
        } elseif (preg_match('/(\*\*\*\s*SEAWAY BILL\s*\*\*\*[\s\S]+?(?:Notification Rule ID:[^\r\n]*|cargo will not be released[^\r\n]*))/i', $body, $matches)) {
            $releaseText = trim($matches[1]);
        } else {
            $releaseText = "*** SEAWAY BILL ***\n\n"
                .($vessel ? "Vessel/Voyage: {$vessel}".($voyage ? "/{$voyage}" : '')."\n" : '')
                .($pol ? "Port of Load: {$pol}\n" : '')
                .($pod ? "Port of Discharge: {$pod}\n" : '')
                .($blNumber ? "BL/Shipment No: {$blNumber}\n" : '')
                ."VIN/Container: {$vin}\n"
                .($pinNumber ? "Pin Number: {$pinNumber}\n\n" : "\n")
                ."The Seaway Bill email & proper identification is to be taken directly to the Release desk.\n"
                ."**Not the customer care desk that PAD (originals) are printed**\n\n"
                ."Please provide the releasing dest agent the PIN associated.\n"
                ."If the PIN is accurate, the Delivery Order will be printed & cargo will be released.\n"
                .'If the PIN is inaccurate, please contact the Shipper as cargo will not be released.';
        }

        // Clean up excessive empty lines and quoted-printable artifacts
        $releaseText = $this->cleanQuotedPrintable($releaseText);
        $releaseText = preg_replace("/\n{3,}/", "\n\n", $releaseText) ?? $releaseText;

        return new CarrierReleaseData(
            carrier: 'grimaldi_acl',
            releaseType: 'seaway_bill',
            vin: $vin,
            blNumber: $blNumber,
            pinNumber: $pinNumber,
            vessel: $vessel,
            voyage: $voyage,
            pol: $pol,
            pod: $pod,
            consignee: $consignee,
            officialReleaseText: $releaseText,
            rawSubject: $subject,
        );
    }

    /**
     * Split raw email into headers and body.
     *
     * @return array{0: string, 1: string}
     */
    protected function splitHeadersAndBody(string $rawMessage): array
    {
        $parts = preg_split('/\r?\n\r?\n/', $rawMessage, 2);

        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
        ];
    }

    /**
     * Extract a header value from raw headers string.
     */
    protected function extractHeader(string $headers, string $name): string
    {
        $pattern = '/^'.preg_quote($name, '/').':\s*(.+)$/im';
        if (preg_match($pattern, $headers, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * Decode MIME header (handling RFC 2047 encoded words).
     */
    protected function decodeMimeHeader(string $header): string
    {
        if (empty($header)) {
            return '';
        }

        if (function_exists('mb_decode_mime_header')) {
            return mb_decode_mime_header($header);
        }

        if (function_exists('iconv_mime_decode')) {
            return iconv_mime_decode($header, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        }

        return $header;
    }

    /**
     * Extract plain text content from MIME body.
     */
    protected function extractTextBody(string $body, string $contentType, string $contentTransferEncoding = ''): string
    {
        // Check for multipart boundary
        if (preg_match('/boundary=["\']?([^"\';\r\n]+)["\']?/i', $contentType, $matches)) {
            $boundary = $matches[1];
            $parts = explode('--'.$boundary, $body);

            foreach ($parts as $part) {
                if (empty(trim($part)) || str_starts_with(trim($part), '--')) {
                    continue;
                }

                [$partHeaders, $partBody] = $this->splitHeadersAndBody($part);
                $partContentType = $this->extractHeader($partHeaders, 'Content-Type');
                $partEncoding = $this->extractHeader($partHeaders, 'Content-Transfer-Encoding');

                if (str_contains(strtolower($partContentType), 'text/plain')) {
                    $decoded = $this->decodeTransferEncoding($partBody, $partEncoding);

                    return $this->cleanQuotedPrintable($decoded);
                }
            }

            // Fallback to text/html if text/plain not found
            foreach ($parts as $part) {
                [$partHeaders, $partBody] = $this->splitHeadersAndBody($part);
                $partContentType = $this->extractHeader($partHeaders, 'Content-Type');
                $partEncoding = $this->extractHeader($partHeaders, 'Content-Transfer-Encoding');

                if (str_contains(strtolower($partContentType), 'text/html')) {
                    $html = $this->decodeTransferEncoding($partBody, $partEncoding);
                    $text = $this->htmlToPlainText($html);

                    return $this->cleanQuotedPrintable($text);
                }
            }
        }

        // Not multipart or no boundary found
        if ($contentTransferEncoding !== '') {
            $body = $this->decodeTransferEncoding($body, $contentTransferEncoding);
        }

        // Convert HTML to clean plain text if content is HTML
        if (str_contains(strtolower($contentType), 'text/html') || str_contains($body, '<pre') || str_contains($body, '<html')) {
            $body = $this->htmlToPlainText($body);
        }

        return $this->cleanQuotedPrintable($body);
    }

    /**
     * Convert HTML content to clean, readable plain text preserving line breaks.
     */
    public function htmlToPlainText(string $html): string
    {
        // Replace break and block tags with newlines
        $text = preg_replace('/<(?:br|br\s*\/|\/p|\/div|\/tr|\/pre|pre)>/i', "\n", $html) ?? $html;

        // Strip remaining HTML tags
        $text = strip_tags($text);

        // Decode HTML entities (e.g. &nbsp;, &amp;)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $text;
    }

    /**
     * Decode body according to Content-Transfer-Encoding.
     */
    protected function decodeTransferEncoding(string $body, string $encoding): string
    {
        return match (strtolower(trim($encoding))) {
            'base64' => base64_decode(trim($body)),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    /**
     * Sanitize and strip any lingering quoted-printable artifacts.
     */
    public function cleanQuotedPrintable(string $text): string
    {
        // Decode quoted-printable hex artifacts if present (e.g. =20, =3D, or soft line breaks =\r?\n)
        if (str_contains($text, '=20') || str_contains($text, "=\n") || str_contains($text, "=\r\n") || str_contains($text, '=3D')) {
            $text = quoted_printable_decode($text);
        }

        // Strip any residual '=20' that might have been preserved
        $text = str_replace('=20', ' ', $text);

        // Strip trailing whitespace from each line
        $text = preg_replace('/[ \t]+$/m', '', $text) ?? $text;

        // Normalize line endings to \n
        $text = str_replace("\r\n", "\n", $text);

        return trim($text);
    }
}
