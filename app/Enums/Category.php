<?php

namespace App\Enums;

enum Category: string
{
	case Produce = 'produce';
	case Dairy = 'dairy';
	case Bakery = 'bakery';
	case Meat = 'meat';
	case Frozen = 'frozen';
	case Other = 'other';
}
