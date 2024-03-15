<?php

namespace App\Models;

use App\Abstractions\AbstractClasses\UserModelAbstract;
use App\Traits\HasRepositoryTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends UserModelAbstract
{
    use HasApiTokens, HasFactory, Notifiable, HasRepositoryTrait;

    /**
     * @var string
     */
    protected string $guard = 'user';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'dob',
        'phone',
    ];

    protected array $relationships = [
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * @return string
     */
    public function getGuard(): string
    {
        return $this->guard;
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
    public function getFirstName(): string
    {
        return $this->attributes['first_name'];
    }

    /**
     * @return string
     */
    public function getLastName(): string
    {
        return $this->attributes['last_name'];
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->attributes['email'];
    }

    /**
     * @return string
     */
    public function getPhone(): string
    {
        return $this->attributes['phone'];
    }

    /**
     * @return string
     */
    public function getDOB(): string
    {
        return $this->attributes['dob'];
    }

    /**
     * @param string $column
     * @param string $value
     * @return Builder|Model|object|null
     */
    public static function getUserByColumnAndValue(string $column, string $value)
    {
        return self::with((new self())->relationships)->where($column, $value)->first();
    }
}
