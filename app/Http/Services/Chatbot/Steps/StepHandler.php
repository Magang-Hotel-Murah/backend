<?php

namespace App\Http\Services\Chatbot\Steps;

use App\Models\User;
use App\Http\Services\Chatbot\ChatbotStateManager;

/**
 * StepHandler - Abstract base class for all conversation step handlers
 *
 * Each step in the conversation flow extends this class and implements
 * the handle() method with its specific logic.
 */
abstract class StepHandler
{
    protected ChatbotStateManager $stateManager;

    public function __construct(ChatbotStateManager $stateManager)
    {
        $this->stateManager = $stateManager;
    }

    /**
     * Handle user input for this step
     *
     * @param string $userId User's phone number
     * @param string $text User's message text
     * @param User $user Authenticated user model
     * @return string Response message to send back
     */
    abstract public function handle(string $userId, string $text, User $user): string;

    /**
     * Transition to next step
     */
    protected function transitionTo(string $userId, string $nextStep): void
    {
        $this->stateManager->setStep($userId, $nextStep);
    }

    /**
     * Store form data
     */
    protected function storeData(string $userId, string $key, $value): void
    {
        $this->stateManager->updateData($userId, $key, $value);
    }

    /**
     * Get stored form data
     */
    protected function getData(string $userId, string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->stateManager->getData($userId);
        }
        return $this->stateManager->getDataValue($userId, $key, $default);
    }
}
