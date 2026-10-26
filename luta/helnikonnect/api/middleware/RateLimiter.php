<?php
/**
 * Basic rate limiting for sensitive routes.
 */
final class RateLimiter
{
    public static function hit(string $identifier, string $route, int $max, int $windowSeconds = 60): void
    {
        $db = Database::connection();
        // Prune old windows
        $db->exec("DELETE FROM rate_limits WHERE window_start < NOW() - INTERVAL $windowSeconds SECOND");

        $stmt = $db->prepare('SELECT hits FROM rate_limits WHERE identifier = ? AND route = ? LIMIT 1');
        $stmt->bindValue(1, $identifier);
        $stmt->bindValue(2, $route);
        $stmt->execute();
        $row = $stmt->fetch();

        if ($row) {
            if ((int) $row['hits'] >= $max) {
                Response::tooManyRequests('Too many attempts. Please try again later.');
            }
            $upd = $db->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE identifier = ? AND route = ?');
            $upd->execute([$identifier, $route]);
        } else {
            $ins = $db->prepare('INSERT INTO rate_limits (identifier, route, hits) VALUES (?, ?, 1)');
            $ins->execute([$identifier, $route]);
        }
    }
}