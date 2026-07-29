<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firebase_push_notifications', function (Blueprint $table) {
            $table->json('dynamic_values')->nullable()->after('value');
        });

        $values = [
            0 => ['{tripId}', '{dropOffLocation}'],
            1 => ['{sentTime}', '{tripId}'],
            2 => ['{sentTime}', '{tripId}'],
            3 => ['{tripId}'],
            4 => ['{tripId}'],
            5 => ['{tripId}'],
            6 => ['{vehicleCategory}', '{pickUpLocation}', '{tripId}'],
            7 => ['{userName}'],
            8 => ['{sentTime}'],
            9 => ['{paidAmount}', '{methodName}', '{tripId}'],
            10 => ['{tripId}', '{approximateAmount}', '{dropOffLocation}', '{pickUpLocation}'],
            11 => ['{tripId}', '{approximateAmount}', '{pickUpLocation}'],
            12 => ['{tripId}'],
            13 => ['{tripId}', '{sentTime}'],
            14 => null,
            15 => ['{tripId}', '{tipsAmount}', '{customerName}'],
            16 => ['{tripId}', '{approximateAmount}'],
            17 => ['{tripId}', '{approximateAmount}'],
            18 => ['{parcelId}'],
            19 => ['{parcelId}'],
            20 => ['{parcelId}'],
            21 => ['{parcelId}'],
            22 => ['{parcelId}', '{sentTime}'],
            23 => ['{parcelId}', '{customerName}'],
            24 => ['{parcelId}', '{otp}'],
            25 => ['{parcelId}'],
            26 => ['{parcelId}'],
            27 => ['{parcelId}', '{approximateAmount}'],
            28 => ['{parcelId}', '{approximateAmount}'],
            29 => ['{parcelId}', '{approximateAmount}', '{dropOffLocation}', '{pickUpLocation}'],
            30 => ['{parcelId}', '{approximateAmount}'],
            31 => ['{parcelId}'],
            32 => ['{parcelId}'],
            33 => ['{approximateAmount}'],
            34 => ['{userName}', '{sentTime}', '{vehicleCategory}'],
            35 => ['{userName}', '{sentTime}', '{vehicleCategory}'],
            36 => ['{userName}', '{sentTime}', '{vehicleCategory}'],
            37 => ['{userName}', '{sentTime}'],
            38 => ['{userName}', '{sentTime}'],
            39 => ['{userName}', '{sentTime}', '{vehicleCategory}'],
            40 => ['{approximateAmount}'],
            41 => null,
            42 => ['{customerName}'],
            43 => ['{driverName}'],
            44 => null,
            45 => ['{referralRewardAmount}'],
            46 => null,
            47 => null,
            48 => null,
            49 => null,
            50 => null,
            51 => ['{userName}', '{sentTime}', '{tripId}'],
            52 => ['{sentTime}', '{driverName}'],
            53 => ['{levelName}'],
            54 => ['{walletAmount}'],
            55 => ['{walletAmount}'],
            56 => ['{withdrawNote}', '{userName}'],
            57 => ['{userName}'],
            58 => ['{userName}'],
            59 => ['{userName}'],
            60 => ['{tripId}'],
            61 => ['{tripId}'],
            62 => ['{tripId}', '{vehicleCategory}', '{pickUpLocation}'],
            63 => ['{tripId}', '{vehicleCategory}', '{pickUpLocation}'],
            64 => ['{tripId}', '{dropOffLocation}'],
            65 => ['{tripId}', '{sentTime}'],
            66 => ['{tripId}', '{sentTime}'],
            67 => ['{tripId}'],
            68 => ['{tripId}'],
            69 => ['{tripId}'],
            70 => ['{paidAmount}', '{methodName}', '{tripId}'],
            71 => ['{tripId}', '{approximateAmount}', '{dropOffLocation}', '{pickUpLocation}'],
            72 => ['{tripId}'],
            73 => ['{tripId}', '{tipsAmount}', '{customerName}'],
            74 => ['{tripId}', '{sentTime}'],
        ];
        $ids = DB::table('firebase_push_notifications')->pluck('id')->toArray();
        $newValues = [];
        foreach ($values as $oldKey => $val) {
            if (isset($ids[$oldKey])) {
                $newKey = $ids[$oldKey];
                $newValues[$newKey] = $val;
            }
        }
        foreach ($newValues as $id => $value) {
            DB::table('firebase_push_notifications')->where('id', $id)->update([
                'dynamic_values' => $value !== null ? json_encode($value) : null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('firebase_push_notifications', function (Blueprint $table) {
            Schema::table('firebase_push_notifications', function (Blueprint $table) {
                $table->dropColumn('dynamic_values');
            });
        });
    }
};
