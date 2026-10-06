<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DeliverySlotController extends Controller
{
    /**
     * Get list of available delivery time windows.
     */
    public function index()
    {
        $slots = [
            [
                'id' => 'asap',
                'title' => 'ASAP',
                'subtitle' => 'In about 45 min',
                'badge' => 'FASTEST',
                'is_default' => true,
                'available' => true
            ],
            [
                'id' => 'today_18_19',
                'title' => '6:00 – 7:00 pm',
                'subtitle' => 'Today',
                'badge' => null,
                'is_default' => false,
                'available' => true
            ],
            [
                'id' => 'today_19_20',
                'title' => '7:00 – 8:00 pm',
                'subtitle' => 'Today',
                'badge' => null,
                'is_default' => false,
                'available' => true
            ],
            [
                'id' => 'tomorrow_09_10',
                'title' => '9:00 – 10:00 am',
                'subtitle' => 'Tomorrow',
                'badge' => null,
                'is_default' => false,
                'available' => true
            ],
            [
                'id' => 'tomorrow_14_15',
                'title' => '2:00 – 3:00 pm',
                'subtitle' => 'Tomorrow',
                'badge' => null,
                'is_default' => false,
                'available' => true
            ]
        ];

        return response()->json([
            'slots' => $slots
        ], 200);
    }
}
