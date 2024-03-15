<?php

namespace App\Models;

use App\Traits\HasRepositoryTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLoan extends Model
{
    use HasFactory, HasRepositoryTrait;

    /**
     * @var array
     */
    public array $relationships = [
        'user'
    ];

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->attributes['id'];
    }

    /**
     * @return string
     */
    public function getReference(): string
    {
        return $this->attributes['reference'];
    }

    /**
     * @return int
     */
    public function getUserId(): int
    {
        return $this->attributes['user_id'];
    }

    /**
     * @return string
     */
    public function getIncome(): string
    {
        return $this->attributes['income'];
    }

    /**
     * @return int
     */
    public function getLoanAmount(): int
    {
        return $this->attributes['loan_amount'];
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->attributes['status'];
    }

    /**
     * @return int
     */
    public function getIsFullyPaid(): int
    {
        return $this->attributes['is_fully_paid'];
    }


    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }
}
