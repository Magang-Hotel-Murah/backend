<?php

namespace App\Http\Services\Chatbot;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * UserValidator - Validates and retrieves user from WhatsApp number
 *
 * Handles phone number normalization and user lookup with caching
 * for performance optimization.
 */
class UserValidator
{
    private const CACHE_TTL_MINUTES = 60;

    /**
     * Validate and retrieve user by WhatsApp number
     *
     * @param string $whatsappNumber WhatsApp number (can include @c.us)
     * @return User|null User model if found, null otherwise
     */
    public function validateUser(string $whatsappNumber): ?User
    {
        $normalizedNumber = $this->normalizePhone($whatsappNumber);

        // Try to get from cache first
        $cacheKey = "chatbot_user_{$normalizedNumber}";

        $user = cache()->remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($normalizedNumber) {
            return $this->findUserByPhone($normalizedNumber);
        });

        if (!$user) {
            Log::warning('User not found for WhatsApp number', [
                'whatsapp_number' => $normalizedNumber
            ]);
        }

        return $user;
    }

    /**
     * Find user by phone number
     */
    private function findUserByPhone(string $normalizedNumber): ?User
    {
        return User::with(['profile', 'company'])
            ->whereHas('profile', function ($query) use ($normalizedNumber) {
                // Match against normalized phone (remove spaces, dashes, plus signs)
                $query->whereRaw(
                    "REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', '') = ?",
                    [$normalizedNumber]
                );
            })
            ->first();
    }

    /**
     * Normalize phone number to standard format (62xxx)
     */
    private function normalizePhone(string $number): string
    {
        // Remove @c.us suffix if present
        $number = str_replace('@c.us', '', $number);

        // Remove all non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $number);

        // Convert to Indonesian format (62xxx)
        if (str_starts_with($number, '0')) {
            $number = '62' . substr($number, 1);
        } elseif (!str_starts_with($number, '62')) {
            $number = '62' . $number;
        }

        return $number;
    }

    /**
     * Check if user has permission to use specific feature
     */
    public function hasPermission(User $user, string $permission): bool
    {
        // Add your permission logic here
        // For example: check roles, permissions, subscription status, etc.

        return true; // Default: all users have access
    }

    /**
     * Get user's company ID
     */
    public function getUserCompanyId(User $user): ?int
    {
        return $user->company_id ?? $user->company?->id;
    }

    /**
     * Clear user cache
     */
    public function clearUserCache(string $whatsappNumber): void
    {
        $normalizedNumber = $this->normalizePhone($whatsappNumber);
        $cacheKey = "chatbot_user_{$normalizedNumber}";
        cache()->forget($cacheKey);
    }
}
