<?php

namespace anvildev\booked\records;

use anvildev\booked\helpers\SecurityCrypto;
use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $employeeId
 * @property string $provider
 * @property string $accessToken
 * @property string|null $refreshToken
 * @property string $expiresAt
 */
class CalendarTokenRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%booked_calendar_tokens}}';
    }

    public function beforeSave($insert): bool
    {
        if ($this->accessToken) {
            $this->accessToken = SecurityCrypto::encrypt($this->accessToken);
        }
        if ($this->refreshToken) {
            $this->refreshToken = SecurityCrypto::encrypt($this->refreshToken);
        }
        return parent::beforeSave($insert);
    }

    public function afterFind(): void
    {
        parent::afterFind();
        if ($this->accessToken) {
            try {
                $this->accessToken = SecurityCrypto::decrypt($this->accessToken);
            } catch (\Throwable) {
                // Token may not be encrypted yet (pre-migration data)
            }
        }
        if ($this->refreshToken) {
            try {
                $this->refreshToken = SecurityCrypto::decrypt($this->refreshToken);
            } catch (\Throwable) {
                // Token may not be encrypted yet (pre-migration data)
            }
        }
    }
}
