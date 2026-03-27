<?php
/**
 * Gestione impostazioni personalizzate da database
 */

require_once __DIR__ . '/db.php';

class Settings {
    private static array $cache = [];

    /**
     * Carica tutte le impostazioni dal database
     */
    public static function load(): void {
        try {
            $rows = DB::query("SELECT setting_key, setting_value FROM settings");
            foreach ($rows as $row) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // Se la tabella non esiste ancora o errore, ignoriamo silenziosamente
        }
    }

    /**
     * Ottiene il valore di un'impostazione
     */
    public static function get(string $key, $default = null) {
        if (empty(self::$cache)) {
            self::load();
        }
        return self::$cache[$key] ?? $default;
    }

    /**
     * Salva un'impostazione
     */
    public static function set(string $key, ?string $value): void {
        $sql = "INSERT INTO settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        DB::execute($sql, [$key, $value]);
        self::$cache[$key] = $value;
    }
}
