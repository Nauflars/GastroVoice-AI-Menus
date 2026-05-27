<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Domain\ValueObject;

enum Intent: string
{
    case CreateReservation  = 'create_reservation';
    case CheckAvailability  = 'check_availability';
    case CreateOrder        = 'create_order';
    case QueryMenu          = 'query_menu';
    case GetRestaurantInfo  = 'get_restaurant_info';
    case Unknown            = 'unknown';
}
