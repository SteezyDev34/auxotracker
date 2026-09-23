<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;

/**
 * Service centralisé de logging d'activité.
 * Bufférise les entrées pour minimiser les requêtes DB pendant les imports.
 */
class ActivityLogger
{
    private static array $buffer = [];
    private static int   $bufferMax = 100;
    private static bool  $enabled   = true;

    public static function enable(): void  { self::$enabled = true; }
    public static function disable(): void { self::$enabled = false; }

    public static function log(
        string $type,
        string $description,
        string $level = 'info',
        array  $options = []
    ): void {
        if (!self::$enabled) return;

        self::$buffer[] = [
            'type'         => $type,
            'sport'        => $options['sport']        ?? null,
            'subject_type' => $options['subject_type'] ?? null,
            'subject_id'   => $options['subject_id']   ?? null,
            'subject_name' => $options['subject_name'] ?? null,
            'subject_img'  => $options['subject_img']  ?? null,
            'description'  => $description,
            'metadata'     => isset($options['metadata']) ? json_encode($options['metadata']) : null,
            'level'        => $level,
            'command'      => $options['command']      ?? null,
            'created_at'   => now()->toDateTimeString(),
            'updated_at'   => now()->toDateTimeString(),
        ];

        if (count(self::$buffer) >= self::$bufferMax) {
            self::flush();
        }
    }

    public static function flush(): void
    {
        if (empty(self::$buffer)) return;
        try {
            DB::table('activity_logs')->insert(self::$buffer);
        } catch (\Throwable $e) {
            // Ne pas planter l'import si le log échoue
        }
        self::$buffer = [];
    }

    // ─── Raccourcis ─────────────────────────────────────────────

    public static function teamCreated(string $name, ?int $id, ?string $img, string $sport, string $command = ''): void
    {
        self::log('team_created', "Équipe créée : {$name}", 'success', [
            'sport' => $sport, 'subject_type' => 'team',
            'subject_id' => $id, 'subject_name' => $name, 'subject_img' => $img, 'command' => $command,
        ]);
    }

    public static function teamUpdated(string $name, ?int $id, ?string $img, string $sport, array $changed = [], string $command = ''): void
    {
        self::log('team_updated', "Équipe mise à jour : {$name}" . (empty($changed) ? '' : ' [' . implode(', ', $changed) . ']'), 'info', [
            'sport' => $sport, 'subject_type' => 'team',
            'subject_id' => $id, 'subject_name' => $name, 'subject_img' => $img,
            'metadata' => ['changed' => $changed], 'command' => $command,
        ]);
    }

    public static function leagueCreated(string $name, ?int $id, ?string $img, string $sport, string $command = ''): void
    {
        self::log('league_created', "Ligue créée : {$name}", 'success', [
            'sport' => $sport, 'subject_type' => 'league',
            'subject_id' => $id, 'subject_name' => $name, 'subject_img' => $img, 'command' => $command,
        ]);
    }

    public static function leagueUpdated(string $name, ?int $id, ?string $img, string $sport, string $command = ''): void
    {
        self::log('league_updated', "Ligue mise à jour : {$name}", 'info', [
            'sport' => $sport, 'subject_type' => 'league',
            'subject_id' => $id, 'subject_name' => $name, 'subject_img' => $img, 'command' => $command,
        ]);
    }

    public static function matchCreated(string $description, ?int $id, string $sport, string $command = '', array $meta = []): void
    {
        self::log('match_created', "Match créé : {$description}", 'success', [
            'sport' => $sport, 'subject_type' => 'match',
            'subject_id' => $id, 'subject_name' => $description,
            'metadata' => $meta, 'command' => $command,
        ]);
    }

    public static function importStarted(string $command, string $sport, array $meta = []): void
    {
        self::log('import_started', "Import démarré : {$command}", 'info', [
            'sport' => $sport, 'command' => $command, 'metadata' => $meta,
        ]);
    }

    public static function importFinished(string $command, string $sport, array $stats = []): void
    {
        $summary = collect($stats)->map(fn($v, $k) => "{$k}: {$v}")->implode(', ');
        self::log('import_finished', "Import terminé : {$command} — {$summary}", 'success', [
            'sport' => $sport, 'command' => $command, 'metadata' => $stats,
        ]);
        self::flush();
    }

    public static function cacheWrite(string $file, int $count, string $sport): void
    {
        self::log('cache_write', "Cache écrit : {$file} ({$count} entrées)", 'info', [
            'sport' => $sport, 'subject_type' => 'cache', 'subject_name' => $file,
            'metadata' => ['count' => $count],
        ]);
    }

    public static function error(string $description, string $sport = '', string $command = '', array $meta = []): void
    {
        self::log('error', $description, 'error', [
            'sport' => $sport, 'command' => $command, 'metadata' => $meta,
        ]);
        self::flush(); // flush immédiatement les erreurs
    }
}
