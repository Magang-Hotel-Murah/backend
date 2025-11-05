<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Http\Services\Chatbot\ChatbotStateManager;
use App\Http\Services\Chatbot\ChatbotFlowHandler;
use App\Http\Services\Chatbot\UserValidator;
use App\Http\Services\Chatbot\Parsers\ParticipantParser;
use App\Http\Services\Chatbot\Parsers\RequestParser;
use App\Http\Services\Chatbot\Parsers\QuickFormParser;

/**
 * ChatbotServiceProvider - Register chatbot services
 *
 * Register all chatbot-related services as singletons for better
 * performance and consistent state management.
 */
class ChatbotServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // State Manager - Singleton for consistent cache handling
        $this->app->singleton(ChatbotStateManager::class, function ($app) {
            return new ChatbotStateManager();
        });

        // User Validator - Singleton for user lookup caching
        $this->app->singleton(UserValidator::class, function ($app) {
            return new UserValidator();
        });

        // Parsers - Singletons as they are stateless
        $this->app->singleton(ParticipantParser::class, function ($app) {
            return new ParticipantParser();
        });

        $this->app->singleton(RequestParser::class, function ($app) {
            return new RequestParser();
        });

        $this->app->singleton(QuickFormParser::class, function ($app) {
            return new QuickFormParser(
                $app->make(ParticipantParser::class),
                $app->make(RequestParser::class),
                $app->make(\App\Http\Services\MeetingRoomReservationService::class)
            );
        });

        // Flow Handler - Main orchestrator
        $this->app->singleton(ChatbotFlowHandler::class, function ($app) {
            return new ChatbotFlowHandler(
                $app->make(ChatbotStateManager::class),
                $app->make(UserValidator::class),
                $app->make(QuickFormParser::class)
            );
        });

        // Register all step handlers
        $this->registerStepHandlers();
    }

    /**
     * Register step handlers
     */
    private function registerStepHandlers(): void
    {
        $stepHandlers = [
            \App\Http\Services\Chatbot\Steps\MenuStep::class,
            \App\Http\Services\Chatbot\Steps\DateStep::class,
            \App\Http\Services\Chatbot\Steps\ParticipantsCountStep::class,
            \App\Http\Services\Chatbot\Steps\RoomSelectionStep::class,
            \App\Http\Services\Chatbot\Steps\TimeSlotStep::class,
            \App\Http\Services\Chatbot\Steps\TitleStep::class,
            \App\Http\Services\Chatbot\Steps\DescriptionStep::class,
            \App\Http\Services\Chatbot\Steps\ParticipantsDetailStep::class,
            \App\Http\Services\Chatbot\Steps\RequestStep::class,
        ];

        foreach ($stepHandlers as $handler) {
            $this->app->bind($handler, function ($app) use ($handler) {
                return new $handler(
                    $app->make(ChatbotStateManager::class),
                    ...$this->resolveHandlerDependencies($handler)
                );
            });
        }
    }

    /**
     * Resolve additional dependencies for specific step handlers
     */
    private function resolveHandlerDependencies(string $handlerClass): array
    {
        $dependencies = [];

        // Map specific dependencies for handlers that need them
        $dependencyMap = [
            \App\Http\Services\Chatbot\Steps\ParticipantsCountStep::class => [
                \App\Http\Controllers\MeetingRoomController::class
            ],
            \App\Http\Services\Chatbot\Steps\ParticipantsDetailStep::class => [
                ParticipantParser::class
            ],
            \App\Http\Services\Chatbot\Steps\RequestStep::class => [
                RequestParser::class,
                \App\Http\Services\MeetingRoomReservationService::class
            ],
        ];

        if (isset($dependencyMap[$handlerClass])) {
            foreach ($dependencyMap[$handlerClass] as $dependency) {
                $dependencies[] = app($dependency);
            }
        }

        return $dependencies;
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        //
    }
}
