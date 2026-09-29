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

    protected ?string $selectedFolder = null;

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
     * Check if socket connection is alive.
     */
    public function isConnected(): bool
    {
        return $this->stream !== null && ! feof($this->stream);
    }

    /**
     * Candidate folders where carrier release emails arrive in Zoho Mail.
     * Sallaum Lines emails arrive in INBOX.
     * Atlas / ACL Cargo automated emails arrive in Notification.
     *
     * @return list<string>
     */
    public function getCandidateFolders(): array
    {
        return ['INBOX', 'Notification'];
    }

    /**
     * Get currently selected mailbox folder.
     */
    public function getSelectedFolder(): ?string
    {
        return $this->selectedFolder;
    }

    /**
     * Connect and authenticate to the IMAP server.
     */
    public function connect(string $folder = 'INBOX'): bool
    {
        if ($this->isConnected()) {
            return $this->selectFolder($folder);
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

        return $this->selectFolder($folder);
    }

    /**
     * Select a specific mailbox folder (e.g. INBOX, Notification).
     */
    public function selectFolder(string $folder = 'INBOX'): bool
    {
        if (! $this->isConnected()) {
            return false;
        }

        if ($this->selectedFolder === $folder) {
            return true;
        }

        $tag = $this->nextTag();
        $this->sendCommand("{$tag} SELECT \"{$folder}\"");
        $selectResponse = $this->readUntilTagged($tag);

        if (! str_contains($selectResponse, "{$tag} OK")) {
            Log::error("ImapMailboxService: Failed to select {$folder}: ".trim($selectResponse));

            return false;
        }

        $this->selectedFolder = $folder;

        return true;
    }

    /**
     * Get list of unseen message UIDs.
     *
     * @return list<int>
     */
    public function getUnseenUids(int $limit = 50): array
    {
        $uids = $this->searchUids('UNSEEN');
        sort($uids);

        return array_slice($uids, 0, $limit);
    }

    /**
     * Search UIDs by arbitrary IMAP criteria.
     *
     * @return list<int>
     */
    public function searchUids(string $criteria): array
    {
        if (! $this->isConnected() && ! $this->connect($this->selectedFolder ?? 'INBOX')) {
            return [];
        }

        $tag = $this->nextTag();
        $this->sendCommand("{$tag} UID SEARCH {$criteria}");
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

        return $uids;
    }

    /**
     * Get candidate release UIDs: combines UNSEEN messages with recent carrier release emails.
     * Ensures releases opened in webmail can still be fulfilled if the shipment is awaiting telex release.
     *
     * @return list<int>
     */
    public function getCandidateReleaseUids(int $limit = 50, int $days = 7): array
    {
        if (! $this->isConnected() && ! $this->connect($this->selectedFolder ?? 'INBOX')) {
            return [];
        }

        $sinceDate = now()->subDays($days)->format('d-M-Y');

        $unseen = $this->searchUids('UNSEEN');
        $telex = $this->searchUids("SINCE {$sinceDate} SUBJECT \"TELEX RELEASE\"");
        $seaway = $this->searchUids("SINCE {$sinceDate} SUBJECT \"SEAWAY BILL\"");
        $atlas = $this->searchUids("SINCE {$sinceDate} FROM \"donotreply@aclcargo.com\"");

        $merged = array_unique(array_merge($unseen, $telex, $seaway, $atlas));
        rsort($merged); // Process newest first

        return array_slice($merged, 0, $limit);
    }

    /**
     * Fetch RFC822 full email content by UID using BODY.PEEK so it remains UNSEEN.
     */
    public function fetchMessageByUid(int $uid): ?string
    {
        if (! $this->isConnected() && ! $this->connect($this->selectedFolder ?? 'INBOX')) {
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
        if (! $this->isConnected() && ! $this->connect($this->selectedFolder ?? 'INBOX')) {
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
                $this->selectedFolder = null;
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
