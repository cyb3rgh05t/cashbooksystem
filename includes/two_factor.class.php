<?php

/**
 * Two-Factor Authentication Helper Class
 * Implements TOTP (Time-based One-Time Password) authentication
 * Compatible with Google Authenticator, Authy, Microsoft Authenticator, etc.
 */

class TwoFactorAuth
{
    private $pdo;
    private $logger;
    private $codeLength = 6;
    private $period = 30; // 30 seconds time window
    private $issuer = 'Cashbook System';

    public function __construct($pdo, $logger = null)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    /**
     * Generate a random secret key for the user
     * 
     * @return string Base32 encoded secret
     */
    public function generateSecret(): string
    {
        $secret = '';
        $validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 alphabet

        for ($i = 0; $i < 16; $i++) {
            $secret .= $validChars[random_int(0, 31)];
        }

        return $secret;
    }

    /**
     * Generate QR code URL for authenticator apps
     * 
     * @param string $username User's username
     * @param string $secret The secret key
     * @return string QR code image URL
     */
    public function getQRCodeUrl(string $username, string $secret): string
    {
        $issuer = urlencode($this->issuer);
        $username = urlencode($username);
        $secret = urlencode($secret);

        // otpauth:// URL format for authenticator apps
        $otpauthUrl = "otpauth://totp/{$issuer}:{$username}?secret={$secret}&issuer={$issuer}";

        // Use Google Charts API to generate QR code
        return "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . urlencode($otpauthUrl);
    }

    /**
     * Verify a TOTP code
     * 
     * @param string $secret The user's secret key
     * @param string $code The code to verify
     * @param int $discrepancy Number of time windows to check (allows for time drift)
     * @return bool True if valid
     */
    public function verifyCode(string $secret, string $code, int $discrepancy = 1): bool
    {
        $currentTimeSlice = floor(time() / $this->period);

        // Check current time slice and adjacent ones to account for time drift
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = $this->getCode($secret, $currentTimeSlice + $i);

            if ($this->timingSafeEquals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify a backup code
     * 
     * @param int $userId User ID
     * @param string $code Backup code to verify
     * @return bool True if valid
     */
    public function verifyBackupCode(int $userId, string $code): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT backup_codes FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || empty($user['backup_codes'])) {
                return false;
            }

            $backupCodes = json_decode($user['backup_codes'], true);

            if (!is_array($backupCodes)) {
                return false;
            }

            // Check if code exists
            $key = array_search($code, $backupCodes);
            if ($key === false) {
                return false;
            }

            // Remove used backup code
            unset($backupCodes[$key]);
            $backupCodes = array_values($backupCodes); // Re-index array

            // Update database
            $stmt = $this->pdo->prepare("UPDATE users SET backup_codes = ? WHERE id = ?");
            $stmt->execute([json_encode($backupCodes), $userId]);

            if ($this->logger) {
                $this->logger->info("Backup code used", $userId, 'TWO_FACTOR', [
                    'remaining_codes' => count($backupCodes)
                ]);
            }

            return true;
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Error verifying backup code: " . $e->getMessage(), $userId, 'TWO_FACTOR');
            }
            return false;
        }
    }

    /**
     * Generate backup codes for the user
     * 
     * @param int $count Number of backup codes to generate
     * @return array Array of backup codes
     */
    public function generateBackupCodes(int $count = 10): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->generateBackupCode();
        }

        return $codes;
    }

    /**
     * Generate a single backup code
     * 
     * @return string 8-character backup code
     */
    private function generateBackupCode(): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';

        for ($i = 0; $i < 8; $i++) {
            $code .= $characters[random_int(0, 35)];
        }

        return $code;
    }

    /**
     * Enable 2FA for a user
     * 
     * @param int $userId User ID
     * @param string $secret Secret key
     * @return array Result with success status and backup codes
     */
    public function enable(int $userId, string $secret): array
    {
        try {
            $backupCodes = $this->generateBackupCodes();

            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET two_factor_enabled = 1,
                    two_factor_secret = ?,
                    backup_codes = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $secret,
                json_encode($backupCodes),
                $userId
            ]);

            if ($this->logger) {
                $this->logger->info("2FA enabled", $userId, 'TWO_FACTOR');
            }

            return [
                'success' => true,
                'backup_codes' => $backupCodes
            ];
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Error enabling 2FA: " . $e->getMessage(), $userId, 'TWO_FACTOR');
            }

            return [
                'success' => false,
                'message' => 'Fehler beim Aktivieren von 2FA'
            ];
        }
    }

    /**
     * Disable 2FA for a user
     * 
     * @param int $userId User ID
     * @return bool Success status
     */
    public function disable(int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET two_factor_enabled = 0,
                    two_factor_secret = NULL,
                    backup_codes = NULL
                WHERE id = ?
            ");

            $stmt->execute([$userId]);

            if ($this->logger) {
                $this->logger->info("2FA disabled", $userId, 'TWO_FACTOR');
            }

            return true;
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("Error disabling 2FA: " . $e->getMessage(), $userId, 'TWO_FACTOR');
            }
            return false;
        }
    }

    /**
     * Check if user has 2FA enabled
     * 
     * @param int $userId User ID
     * @return bool True if 2FA is enabled
     */
    public function isEnabled(int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            return $user && $user['two_factor_enabled'] == 1;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get user's 2FA secret
     * 
     * @param int $userId User ID
     * @return string|null Secret or null if not found
     */
    public function getSecret(int $userId): ?string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT two_factor_secret FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            return $user ? $user['two_factor_secret'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Generate TOTP code for a given secret and time slice
     * 
     * @param string $secret Base32 encoded secret
     * @param int $timeSlice Time slice
     * @return string Generated code
     */
    private function getCode(string $secret, int $timeSlice): string
    {
        // Decode base32 secret
        $secretKey = $this->base32Decode($secret);

        // Pack time into binary string
        $time = pack('N*', 0, $timeSlice);

        // Generate HMAC-SHA1 hash
        $hash = hash_hmac('sha1', $time, $secretKey, true);

        // Dynamic truncation
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % pow(10, $this->codeLength);

        return str_pad((string)$code, $this->codeLength, '0', STR_PAD_LEFT);
    }

    /**
     * Decode base32 string
     * 
     * @param string $secret Base32 encoded string
     * @return string Decoded binary string
     */
    private function base32Decode(string $secret): string
    {
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsFlipped = array_flip(str_split($base32chars));

        $paddingCharCount = substr_count($secret, '=');
        $allowedValues = [6, 4, 3, 1, 0];

        if (!in_array($paddingCharCount, $allowedValues)) {
            return '';
        }

        for ($i = 0; $i < 4; $i++) {
            if (
                $paddingCharCount == $allowedValues[$i] &&
                substr($secret, - ($allowedValues[$i])) != str_repeat('=', $allowedValues[$i])
            ) {
                return '';
            }
        }

        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = '';

        for ($i = 0; $i < count($secret); $i = $i + 8) {
            $x = '';
            if (!in_array($secret[$i], str_split($base32chars))) {
                return '';
            }

            for ($j = 0; $j < 8; $j++) {
                $x .= str_pad(base_convert(@$base32charsFlipped[@$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
            }

            $eightBits = str_split($x, 8);

            for ($z = 0; $z < count($eightBits); $z++) {
                $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';
            }
        }

        return $binaryString;
    }

    /**
     * Timing-safe string comparison
     * 
     * @param string $safe Known string
     * @param string $user User-provided string
     * @return bool True if equal
     */
    private function timingSafeEquals(string $safe, string $user): bool
    {
        if (function_exists('hash_equals')) {
            return hash_equals($safe, $user);
        }

        $safeLen = strlen($safe);
        $userLen = strlen($user);

        if ($userLen != $safeLen) {
            return false;
        }

        $result = 0;

        for ($i = 0; $i < $userLen; $i++) {
            $result |= (ord($safe[$i]) ^ ord($user[$i]));
        }

        return $result === 0;
    }
}
