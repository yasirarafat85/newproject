<?php
declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->lastPage();
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : (($this->page - 1) * $this->perPage) + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->page * $this->perPage);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /** বর্তমান query string ধরে রেখে শুধু page বদলায়। */
    public function url(int $page, array $query = []): string
    {
        $query['page'] = $page;

        return '?' . http_build_query($query);
    }

    /** ১ … ৪ ৫ ৬ … ২০ ধরনের সংক্ষিপ্ত পেজ তালিকা। */
    public function window(int $each = 2): array
    {
        $last = $this->lastPage();
        $pages = [];

        for ($i = 1; $i <= $last; $i++) {
            if ($i === 1 || $i === $last || abs($i - $this->page) <= $each) {
                $pages[] = $i;
            } elseif (end($pages) !== null && end($pages) !== '…') {
                $pages[] = '…';
            }
        }

        return $pages;
    }
}
