<?php

namespace App\Enums;

/**
 * Catalog price filter. Values are the public query-string tokens (?harga=...).
 */
enum CoursePriceFilter: string
{
    case Free = 'gratis';
    case Paid = 'berbayar';
}
