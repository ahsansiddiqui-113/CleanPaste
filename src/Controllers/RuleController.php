<?php
declare(strict_types=1);
namespace CloakPaste\Controllers;

use CloakPaste\Config\Database;
use CloakPaste\Support\{Http, Session};

final class RuleController
{
    private const MAX_RULES = 50;
    private const MAX_PATTERN = 200;

    public function handle()
    {
        $user = Session::user();
        if (!$user) Http::error('Log in required', 401);
        if (!$user['is_pro']) Http::error('Custom rules require a Pro plan', 403);

        $method = $_SERVER['REQUEST_METHOD'];
        if ($method !== 'GET') Http::requireCsrf();
        $uid = $user['id'];
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        match ($method) {
            'GET'    => $this->list($uid),
            'POST'   => $this->create($uid),
            'PUT'    => $id ? $this->update($uid, $id) : Http::error('Missing id', 400),
            'DELETE' => $id ? $this->delete($uid, $id) : Http::error('Missing id', 400),
            default  => Http::error('Method not allowed', 405),
        };
    }

    private function list(int $uid)
    {
        $st = Database::getInstance()->prepare('SELECT id, rule_name, pattern, is_active FROM saved_rules WHERE user_id = ? ORDER BY id');
        $st->execute([$uid]);
        Http::json(['rules' => array_map([$this, 'shape'], $st->fetchAll())]);
    }

    private function create(int $uid)
    {
        $b = Http::body();
        [$name, $pattern] = $this->validated($b['rule_name'] ?? '', $b['pattern'] ?? '');
        $db = Database::getInstance();
        $c = $db->prepare('SELECT COUNT(*) FROM saved_rules WHERE user_id = ?');
        $c->execute([$uid]);
        if ((int)$c->fetchColumn() >= self::MAX_RULES) Http::error('Rule limit reached (' . self::MAX_RULES . ')', 422);

        $db->prepare('INSERT INTO saved_rules (user_id, rule_name, pattern) VALUES (?, ?, ?)')->execute([$uid, $name, $pattern]);
        Http::json(['rule' => ['id' => (int)$db->lastInsertId(), 'rule_name' => $name, 'pattern' => $pattern, 'is_active' => true]], 201);
    }

    private function update(int $uid, int $id)
    {
        $db = Database::getInstance();
        $st = $db->prepare('SELECT id, rule_name, pattern, is_active FROM saved_rules WHERE id = ? AND user_id = ?');
        $st->execute([$id, $uid]);
        $row = $st->fetch();
        if (!$row) Http::error('Rule not found', 404);

        $b = Http::body();
        [$name, $pattern] = $this->validated($b['rule_name'] ?? $row['rule_name'], $b['pattern'] ?? $row['pattern']);
        $active = array_key_exists('is_active', $b) ? (int)(bool)$b['is_active'] : (int)$row['is_active'];
        $db->prepare('UPDATE saved_rules SET rule_name = ?, pattern = ?, is_active = ? WHERE id = ? AND user_id = ?')
           ->execute([$name, $pattern, $active, $id, $uid]);
        Http::json(['rule' => ['id' => $id, 'rule_name' => $name, 'pattern' => $pattern, 'is_active' => (bool)$active]]);
    }

    private function delete(int $uid, int $id)
    {
        $st = Database::getInstance()->prepare('DELETE FROM saved_rules WHERE id = ? AND user_id = ?');
        $st->execute([$id, $uid]);
        if (!$st->rowCount()) Http::error('Rule not found', 404);
        Http::json(['ok' => true]);
    }

    private function validated(mixed $name, mixed $pattern): array
    {
        $name = trim((string)$name);
        $pattern = (string)$pattern;
        if ($name === '' || mb_strlen($name) > 100) Http::error('Rule name must be 1-100 characters', 422);
        if ($pattern === '' || strlen($pattern) > self::MAX_PATTERN) Http::error('Pattern must be 1-' . self::MAX_PATTERN . ' characters', 422);
        // Syntax sanity check only. Patterns run in the user's own browser, never on this server.
        if (@preg_match('~' . str_replace('~', '\~', $pattern) . '~u', '') === false) Http::error('Pattern is not a valid regular expression', 422);
        return [$name, $pattern];
    }

    private function shape(array $r): array
    {
        return ['id' => (int)$r['id'], 'rule_name' => $r['rule_name'], 'pattern' => $r['pattern'], 'is_active' => (bool)$r['is_active']];
    }
}
