<?php
declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * পাতলা, নিরাপদ কোয়েরি বিল্ডার।
 *
 * মান সবসময় placeholder হয়ে যায়; কেবল শনাক্তকারী (টেবিল/কলাম/ডিরেকশন)
 * SQL-এ সরাসরি বসে, আর সেগুলো কঠোর প্যাটার্নে যাচাই করা হয় — তাই
 * ব্যবহারকারীর ইনপুট থেকে আসা sort কলামও ইনজেকশন ঘটাতে পারে না।
 */
final class QueryBuilder
{
    private const OPERATORS = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS', 'IS NOT'];

    private string $table;
    private array $columns = ['*'];
    private array $joins = [];
    private array $wheres = [];
    private array $bindings = [];
    private array $groups = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(string $table)
    {
        $this->table = $this->identifier($table);
    }

    public static function table(string $table): self
    {
        return new self($table);
    }

    public function select(string ...$columns): self
    {
        $this->columns = array_map(fn (string $c): string => $this->column($c), $columns);

        return $this;
    }

    /** কাঁচা SELECT প্রকাশ (COUNT, SUM ইত্যাদি) — কখনো ব্যবহারকারীর ইনপুট দেবেন না। */
    public function selectRaw(string $expression): self
    {
        $this->columns = [$expression];

        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $type = strtoupper($type);
        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT'], true)) {
            throw new InvalidArgumentException("অবৈধ join টাইপ: {$type}");
        }

        $this->joins[] = sprintf(
            '%s JOIN %s ON %s %s %s',
            $type,
            $this->identifier($table),
            $this->column($first),
            $this->operator($operator),
            $this->column($second)
        );

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function where(string $column, mixed $operator = null, mixed $value = null): self
    {
        // where('col', 'value') — অপারেটর বাদ দিলে '=' ধরা হয়
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'boolean' => 'AND',
            'sql'     => sprintf('%s %s ?', $this->column($column), $this->operator((string) $operator)),
        ];
        $this->bindings[] = $value;

        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'boolean' => 'OR',
            'sql'     => sprintf('%s %s ?', $this->column($column), $this->operator((string) $operator)),
        ];
        $this->bindings[] = $value;

        return $this;
    }

    public function whereIn(string $column, array $values, bool $not = false): self
    {
        if ($values === []) {
            // খালি IN () অবৈধ SQL — কোনো ফলই না মেলা বোঝাতে অসম্ভব শর্ত বসাই
            $this->wheres[] = ['boolean' => 'AND', 'sql' => $not ? '1 = 1' : '1 = 0'];

            return $this;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = [
            'boolean' => 'AND',
            'sql'     => sprintf('%s %sIN (%s)', $this->column($column), $not ? 'NOT ' : '', $placeholders),
        ];
        foreach ($values as $value) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function whereNull(string $column, bool $not = false): self
    {
        $this->wheres[] = [
            'boolean' => 'AND',
            'sql'     => sprintf('%s IS %sNULL', $this->column($column), $not ? 'NOT ' : ''),
        ];

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        return $this->whereNull($column, true);
    }

    /** একাধিক শর্ত বন্ধনীতে মুড়ে দেয় — OR গ্রুপের জন্য। */
    public function whereGroup(callable $callback, string $boolean = 'AND'): self
    {
        $nested = new self($this->rawTable());
        $callback($nested);

        [$sql, $bindings] = $nested->compileWheresForNesting();
        if ($sql === '') {
            return $this;
        }

        $this->wheres[] = ['boolean' => strtoupper($boolean) === 'OR' ? 'OR' : 'AND', 'sql' => '(' . $sql . ')'];
        foreach ($bindings as $binding) {
            $this->bindings[] = $binding;
        }

        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->groups[] = $this->column($column);
        }

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = $this->column($column) . ' ' . $direction;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . ' FROM ' . $this->table;

        if ($this->joins !== []) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $wheres = $this->compileWheres();
        if ($wheres !== '') {
            $sql .= ' WHERE ' . $wheres;
        }

        if ($this->groups !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    public function bindings(): array
    {
        return $this->bindings;
    }

    public function get(): array
    {
        return Database::select($this->toSql(), $this->bindings);
    }

    public function first(): ?array
    {
        return $this->limit(1)->get()[0] ?? null;
    }

    public function value(string $column): mixed
    {
        $row = $this->select($column)->first();

        return $row === null ? null : reset($row);
    }

    public function count(string $column = '*'): int
    {
        $clone = clone $this;
        $clone->orders = [];
        $clone->limit = null;
        $clone->offset = null;
        $clone->columns = [$column === '*' ? 'COUNT(*)' : 'COUNT(' . $this->column($column) . ')'];

        return (int) Database::scalar($clone->toSql(), $clone->bindings);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function paginate(int $page, int $perPage = 25): Paginator
    {
        $total = $this->count();
        $page = max(1, $page);
        $rows = $this->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return new Paginator($rows, $total, $page, $perPage);
    }

    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', array_map(fn (string $c): string => $this->column($c), $columns)),
            implode(', ', array_fill(0, count($columns), '?'))
        );

        Database::run($sql, array_values($data));

        return Database::lastInsertId();
    }

    public function update(array $data): int
    {
        if ($data === []) {
            return 0;
        }

        $assignments = [];
        $bindings = [];
        foreach ($data as $column => $value) {
            $assignments[] = $this->column($column) . ' = ?';
            $bindings[] = $value;
        }

        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $assignments);

        $wheres = $this->compileWheres();
        if ($wheres !== '') {
            $sql .= ' WHERE ' . $wheres;
        }

        return Database::statement($sql, array_merge($bindings, $this->bindings));
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->table;

        $wheres = $this->compileWheres();
        if ($wheres !== '') {
            $sql .= ' WHERE ' . $wheres;
        }

        return Database::statement($sql, $this->bindings);
    }

    private function compileWheres(): string
    {
        $sql = '';
        foreach ($this->wheres as $index => $where) {
            $sql .= ($index === 0 ? '' : ' ' . $where['boolean'] . ' ') . $where['sql'];
        }

        return $sql;
    }

    /** @return array{0:string,1:array} */
    private function compileWheresForNesting(): array
    {
        return [$this->compileWheres(), $this->bindings];
    }

    private function rawTable(): string
    {
        return trim($this->table, '`');
    }

    private function identifier(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("অবৈধ টেবিল নাম: {$name}");
        }

        return '`' . $name . '`';
    }

    /** `table.column`, `column` অথবা `column AS alias` — সবই হোয়াইটলিস্ট করা। */
    private function column(string $name): string
    {
        if ($name === '*') {
            return '*';
        }

        $alias = null;
        if (preg_match('/^(.+?)\s+AS\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $name, $matches)) {
            $name = trim($matches[1]);
            $alias = $matches[2];
        }

        $parts = explode('.', $name);
        if (count($parts) > 2) {
            throw new InvalidArgumentException("অবৈধ কলাম নাম: {$name}");
        }

        $compiled = implode('.', array_map(function (string $part): string {
            if ($part === '*') {
                return '*';
            }
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
                throw new InvalidArgumentException("অবৈধ কলাম নাম: {$part}");
            }

            return '`' . $part . '`';
        }, $parts));

        return $alias === null ? $compiled : $compiled . ' AS `' . $alias . '`';
    }

    private function operator(string $operator): string
    {
        $operator = strtoupper(trim($operator));
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("অবৈধ অপারেটর: {$operator}");
        }

        return $operator;
    }
}
