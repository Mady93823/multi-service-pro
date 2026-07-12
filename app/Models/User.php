<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Columns Larastan cannot see (not mass-assignable on purpose).
 *
 * @property bool $is_active
 * @property string|null $blocked_reason
 * @property string|null $referral_code
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * `is_active` is a database default, and a database default is never
     * hydrated back onto the instance that inserted the row — a freshly created
     * User would read `null` and `EnsureUserActive` (M17) would log it straight
     * back out. Default it on the model too.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * @return HasOne<Address, $this>
     */
    public function defaultAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('is_default', true);
    }

    /**
     * Bookings placed by this user as a customer.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    /**
     * Providers this user has favorited (M04; dispatch prefers them in M06).
     *
     * @return BelongsToMany<User, $this>
     */
    public function favoriteProviders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorite_providers', 'customer_id', 'provider_id')
            ->withTimestamps();
    }

    public function hasFavorited(User $provider): bool
    {
        return $this->favoriteProviders()->whereKey($provider->id)->exists();
    }

    /**
     * Onboarding/KYC profile for provider-role users (M05).
     *
     * @return HasOne<ProviderProfile, $this>
     */
    public function providerProfile(): HasOne
    {
        return $this->hasOne(ProviderProfile::class);
    }

    /**
     * Registered web/mobile push tokens (M11 FCM channel).
     *
     * @return HasMany<FcmToken, $this>
     */
    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FcmToken::class);
    }

    /**
     * Refund/credit wallet (M08). Created lazily by WalletService::for().
     *
     * @return HasOne<Wallet, $this>
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Provider ledger (M09) — append-only, one row per completed job plus any
     * compensating rows.
     *
     * @return HasMany<Earning, $this>
     */
    public function earnings(): HasMany
    {
        return $this->hasMany(Earning::class, 'provider_id');
    }

    /**
     * @return HasMany<PayoutRequest, $this>
     */
    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class, 'provider_id');
    }
}
