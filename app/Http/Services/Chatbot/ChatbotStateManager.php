<?php

namespace App\Http\Services\Chatbot;

use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * ChatbotStateManager - Centralized state management for chatbot conversations
 *
 * Handles all cache operations for user conversation state with consistent
 * key naming and TTL management.
 */
class ChatbotStateManager
{
    private const CACHE_TTL_MINUTES = 30;
    private const STATE_PREFIX = 'chatbot_state_';
    private const DATA_PREFIX = 'chatbot_data_';

    /**
     * Get user's current conversation state
     */
    public function getState(string $userId): array
    {
        $state = Cache::get($this->getStateKey($userId));

        if (!$state) {
            return $this->getDefaultState();
        }

        return $state;
    }

    /**
     * Set user's conversation state
     */
    public function setState(string $userId, array $state): void
    {
        Cache::put(
            $this->getStateKey($userId),
            $state,
            Carbon::now()->addMinutes(self::CACHE_TTL_MINUTES)
        );
    }

    /**
     * Update specific field in user's state
     */
    public function updateState(string $userId, string $key, $value): void
    {
        $state = $this->getState($userId);
        $state[$key] = $value;
        $this->setState($userId, $state);
    }

    /**
     * Get accumulated conversation data (e.g., form inputs)
     */
    public function getData(string $userId): array
    {
        return Cache::get($this->getDataKey($userId), []);
    }

    /**
     * Set conversation data
     */
    public function setData(string $userId, array $data): void
    {
        Cache::put(
            $this->getDataKey($userId),
            $data,
            Carbon::now()->addMinutes(self::CACHE_TTL_MINUTES)
        );
    }

    /**
     * Update specific field in conversation data
     */
    public function updateData(string $userId, string $key, $value): void
    {
        $data = $this->getData($userId);
        $data[$key] = $value;
        $this->setData($userId, $data);
    }

    /**
     * Get a specific value from conversation data
     */
    public function getDataValue(string $userId, string $key, $default = null)
    {
        $data = $this->getData($userId);
        return $data[$key] ?? $default;
    }

    /**
     * Clear all state and data for a user
     */
    public function resetUser(string $userId): void
    {
        Cache::forget($this->getStateKey($userId));
        Cache::forget($this->getDataKey($userId));
    }

    /**
     * Check if user has active conversation
     */
    public function hasActiveConversation(string $userId): bool
    {
        return Cache::has($this->getStateKey($userId));
    }

    /**
     * Get current step from state
     */
    public function getCurrentStep(string $userId): string
    {
        $state = $this->getState($userId);
        return $state['step'] ?? 'menu';
    }

    /**
     * Set current step in state
     */
    public function setStep(string $userId, string $step): void
    {
        $this->updateState($userId, 'step', $step);
    }

    /**
     * Store temporary data with custom key
     */
    public function setTempData(string $userId, string $key, $value, int $minutes = null): void
    {
        $ttl = $minutes ? Carbon::now()->addMinutes($minutes) : Carbon::now()->addMinutes(self::CACHE_TTL_MINUTES);
        Cache::put($this->getTempKey($userId, $key), $value, $ttl);
    }

    /**
     * Get temporary data
     */
    public function getTempData(string $userId, string $key, $default = null)
    {
        return Cache::get($this->getTempKey($userId, $key), $default);
    }

    /**
     * Clear temporary data
     */
    public function clearTempData(string $userId, string $key): void
    {
        Cache::forget($this->getTempKey($userId, $key));
    }

    /**
     * Get default state structure
     */
    private function getDefaultState(): array
    {
        return [
            'step' => 'menu',
            'created_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Generate cache key for state
     */
    private function getStateKey(string $userId): string
    {
        return self::STATE_PREFIX . $userId;
    }

    /**
     * Generate cache key for data
     */
    private function getDataKey(string $userId): string
    {
        return self::DATA_PREFIX . $userId;
    }

    /**
     * Generate cache key for temporary data
     */
    private function getTempKey(string $userId, string $key): string
    {
        return "chatbot_temp_{$userId}_{$key}";
    }
}
