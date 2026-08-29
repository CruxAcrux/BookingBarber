<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Barber = 'barber';
    case Admin = 'admin';
}
