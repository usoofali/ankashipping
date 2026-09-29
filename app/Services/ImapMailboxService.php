<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class ImapMailboxService
{
    /**
     * @var resource|null
     */
    protected $stream = null;

    protected int $tagCounter = 1;

    public function __construct(
        protected ?string $host = null,
        protected ?int $port = null,
        protected ?string $username = null,
        protected ?string $password = null,
    ) {
        $this->host = $host ?? (string) env('MAIL_ACCOUNTS_HOST', 'imap.zoho.com');
        $this->port = $port ?? (int) env('MAIL_ACCOUNTS_PORT', 993);
        $this->username = $username ?? (string) env('MAIL_ACCOUNTS_USERNAME', 'accounts@ankshipping.com');
        $this->password = $password ?? (string) env('MAIL_ACCOUNTS_PASSWORD', '');
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Connect and authenticate to the IMAP server.
     */
    public function connect(): bool
    {
        if ($this->stream !== null) {
            return true;
        }

        if (empty($this->username) || empty($this->password)) {
            Log::warning('ImapMailboxService: Missing credentials for accounts mailbox.');

            return false;
        }

        $address = "ssl://{$this->host}:{$this->port}";
        $errno = 0;
        $errstr = '';

        $stream = @stream_socket_client($address, $errno, $errstr, 15);
        if (! $stream) {
            Log::error("ImapMailboxService: Connection to {$address} failed: {$errstr} ({$errno})");

            return false;
        }

        stream_set_timeout($stream, 20);
        $this->stream = $stream;

        // Read server greeting
        $greeting = fgets($this->stream);
        if (! $greeting || ! str_contains($greeting, '* OK')) {
            Log::error('ImapMailboxService: Invalid server greeting: '.trim((string) $greeting));
            $this->disconnect();

            return false;
        }

        // Authenticate
        $tag = $this->nextTag();
        $this->sendCommand("{$tag} LOGIN {$this->username} {$this->password}");
        $loginResponse = $this->readUntilTagged($tag);

        if (! str_contains($loginResponse, "{$tag} OK")) {
            Log::error('ImapMailboxService: Authentication failed: '.trim($loginResponse));
            $this->disconnect();

            return false;
        }

        // Select INBOX
        $tag = $this->nextTag();
        $this->sendCommand("{$tag} SELECT INBOX");
        $selectResponse = $this->readUntilTagged($tag);

        if (! str_contains($selectResponse, "{$tag} OK")) {
            Log::error('ImapMailboxService: Failed to select INBOX: '.trim($selectResponse));
            $this->disconnect();

            return false;
        }

        return true;
    }

    /**
     * Get list of unseen message UIDs.
     *
     * @return list<int>
     */
    public function getUnseenUids(int $limit = 50): array
    {
        if (! $this->connect()) {
            return [];
        }

        $tag = $this->nextTag();
        $this->sendCommand("{$tag} UID SEARCH UNSEEN");
        $searchResponse = $this->readUntilTagged($tag);

        $uids = [];
        $lines = explode("\n", $searchResponse);
        foreach ($lines as $line) {
            if (str_starts_with($line, '* SEARCH')) {
                $parts = explode(' ', trim($line));
                array_shift($parts); // remove '*'
                array_shift($parts); // remove 'SEARCH'

                foreach ($parts as $part) {
                    if (is_numeric($part)) {
                        $uids[] = (int) $part;
                    }
                }
            }
        }

        sort($uids);

        return array_slice($uids, 0, $limit);
    }

    /**
     * Fetch RFC822 full email content by UID using BODY.PEEK so it remains UNSEEN.
     */
    public function fetchMessageByUid(int $uid): ?string
    {
        if (! $this->connect()) {
            return null;
        }

        $tag = $this->nextTag();
        $this->sendCommand("{$tag} UID FETCH {$uid} (BODY.PEEK[])");

        $rawResponse = '';
        $literalBytes = null;

        while ($this->stream && ! feof($this->stream)) {
            $line = fgets($this->stream);
            if ($line === false) {
                break;
            }

            if ($literalBytes === null && preg_match('/\{(\d+)\}/', $line, $matches)) {
                $literalBytes = (int) $matches[1];
                $content = '';
                while ($literalBytes > 0 && ! feof($this->stream)) {
                    $chunk = fread($this->stream, min($literalBytes, 8192));
                    if ($chunk === false) {
                        break;
                    }
                    $content .= $chunk;
                    $literalBytes -= strlen($chunk);
                }
                $rawResponse .= $content;
            } else {
                $rawResponse .= $line;
            }

            if (str_starts_with(trim($line), $tag)) {
                break;
            }
        }

        return ! empty($rawResponse) ? $rawResponse : null;
    }

    /**
     * Mark an email message UID as Seen (\Seen flag).
     */
    public function markAsSeen(int $uid): bool
    {
        if (! $this->connect()) {
            return false;
        }

        $tag = $this->nextTag();
        $this->sendCommand("{$tag} UID STORE {$uid} +FLAGS (\\Seen)");
        $response = $this->readUntilTagged($tag);

        return str_contains($response, "{$tag} OK");
    }

    /**
     * Disconnect gracefully from the IMAP server.
     */
    public function disconnect(): void
    {
        if ($this->stream !== null) {
            try {
                $tag = $this->nextTag();
                $this->sendCommand("{$tag} LOGOUT");
                @fclose($this->stream);
            } catch (Exception) {
                // Ignore disconnect errors
            } finally {
                $this->stream = null;
            }
        }
    }

    protected function nextTag(): string
    {
        return sprintf('A%03d', $this->tagCounter++);
    }

    protected function sendCommand(string $command): void
    {
        if ($this->stream !== null) {
            fwrite($this->stream, $command."\r\n");
        }
    }

    protected function readUntilTagged(string $tag): string
    {
        $buffer = '';
        while ($this->stream && ! feof($this->stream)) {
            $line = fgets($this->stream);
            if ($line === false) {
                break;
            }
            $buffer .= $line;
            if (str_starts_with(trim($line), $tag)) {
                break;
            }
        }

        return $buffer;
    }
}
