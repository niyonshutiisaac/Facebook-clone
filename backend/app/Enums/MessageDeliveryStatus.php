<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageDeliveryStatus: int
{
    case Pending = 0;
    case Sent = 1;
    case Delivered = 2;
    case Read = 3;
}
