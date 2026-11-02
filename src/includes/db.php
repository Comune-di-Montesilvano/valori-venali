<?php
/**
 * Singleton PDO — connessione MariaDB
 */

require_once __DIR__ . '/config.php';

class DB {
    private static ?PDO $instance = null;

    public static function get(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                DB_HOST, DB_PORT, DB_NAME
            );
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(503);
                die('<div style="font-family:sans-serif;padding:2rem;color:#c00;">
                    <h2>Errore di connessione al database</h2>
                    <p>Il servizio non è al momento disponibile. Riprovare tra qualche istante.</p>
                </div>');
            }
        }
        return self::$instance;
    }

    /**
     * Esegue una query con prepared statement e restituisce tutti i risultati
     *
     * @param  list<mixed> $params
     * @return list<array<string, mixed>>
     */
    public static function query(string $sql, array $params = []): array {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
    }

    /**
     * Esegue una query e restituisce una singola riga
     *
     * @param  list<mixed> $params
     * @return array<string, mixed>|null
     */
    public static function queryOne(string $sql, array $params = []): ?array {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Esegue INSERT/UPDATE/DELETE e restituisce il numero di righe affected
     *
     * @param list<mixed> $params
     */
    public static function execute(string $sql, array $params = []): int {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Restituisce l'ultimo ID inserito
     */
    public static function lastInsertId(): string {
        return (string) self::get()->lastInsertId();
    }
}
