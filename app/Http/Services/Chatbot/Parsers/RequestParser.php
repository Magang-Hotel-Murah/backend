<?php

namespace App\Http\Services\Chatbot\Parsers;

/**
 * RequestParser - Parses request/facility input from user messages
 *
 * Format: funds_amount,funds_reason;snack1,snack2;equipment1,equipment2
 * Example: 50000,Snack untuk meeting;Kopi,Teh;Proyektor,Mic
 */
class RequestParser
{
    /**
     * Parse request string into structured array
     *
     * @param string $input Raw input from user
     * @return array Structured request data
     * @throws \InvalidArgumentException If input is invalid
     */
    public function parse(string $input): array
    {
        if (empty(trim($input))) {
            return [];
        }

        // Split by semicolon: funds;snacks;equipment
        $parts = array_map('trim', explode(';', $input));

        $request = [
            'funds_amount' => null,
            'funds_reason' => null,
            'snacks' => [],
            'equipment' => [],
        ];

        // Parse funds (first part)
        if (isset($parts[0]) && !empty($parts[0])) {
            $funds = array_map('trim', explode(',', $parts[0]));

            if (isset($funds[0]) && is_numeric($funds[0])) {
                $request['funds_amount'] = (float)$funds[0];
            }

            if (isset($funds[1]) && !empty($funds[1])) {
                $request['funds_reason'] = $funds[1];
            }
        }

        // Parse snacks (second part)
        if (isset($parts[1]) && !empty($parts[1])) {
            $request['snacks'] = array_filter(
                array_map('trim', explode(',', $parts[1]))
            );
        }

        // Parse equipment (third part)
        if (isset($parts[2]) && !empty($parts[2])) {
            $request['equipment'] = array_filter(
                array_map('trim', explode(',', $parts[2]))
            );
        }

        return $request;
    }

    /**
     * Validate request data structure
     */
    public function validate(array $request): bool
    {
        // If funds_amount is provided, reason should also be provided
        if (!empty($request['funds_amount']) && empty($request['funds_reason'])) {
            return false;
        }

        // Validate funds_amount is positive if provided
        if (isset($request['funds_amount']) && $request['funds_amount'] <= 0) {
            return false;
        }

        return true;
    }
}
