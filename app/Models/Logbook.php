<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Logbook extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'logbooks';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'livestock_id',
        'event_date',
        'event_type',
        'description',
        'handling',
        'new_tag',
        'new_pen_id',
        'new_pen_category',
        'officer_name',
        'pregnancy_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'event_date'     => 'date',
        'pregnancy_date' => 'date',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<int, string>
     */
    protected $dates = [
        'event_date',
        'pregnancy_date',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the livestock that owns this logbook entry.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function livestock(): BelongsTo
    {
        return $this->belongsTo(Livestock::class, 'livestock_id');
    }

    /**
     * Get the new pen (if moved) associated with this logbook entry.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function newPen(): BelongsTo
    {
        return $this->belongsTo(Pen::class, 'new_pen_id');
    }

    /**
     * Scope a query to filter by event type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope a query to filter by date range.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $startDate
     * @param  string  $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateBetween($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('event_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to filter by livestock.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $livestockId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForLivestock($query, int $livestockId)
    {
        return $query->where('livestock_id', $livestockId);
    }

    /**
     * Accessor to get formatted event date.
     *
     * @return string
     */
    public function getFormattedEventDateAttribute(): string
    {
        return $this->event_date ? $this->event_date->format('d/m/Y') : '';
    }

    /**
     * Accessor to get formatted pregnancy date.
     *
     * @return string
     */
    public function getFormattedPregnancyDateAttribute(): string
    {
        return $this->pregnancy_date ? $this->pregnancy_date->format('d/m/Y') : '';
    }
}
