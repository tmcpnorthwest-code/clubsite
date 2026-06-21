<?php
/**
 * Minimal test runner for WP-CLI eval-file execution.
 * No dependencies — pure PHP.
 */
class TestRunner {
    private int   $pass    = 0;
    private int   $fail    = 0;
    private array $failures = [];
    private string $current_suite = '';

    public function suite(string $name): void {
        $this->current_suite = $name;
        echo "\n\033[1;34m══ {$name} ══\033[0m\n";
    }

    public function ok(bool $condition, string $label, string $detail = ''): void {
        if ($condition) {
            echo "  \033[32m✓\033[0m {$label}\n";
            $this->pass++;
        } else {
            $msg = $detail ? " → {$detail}" : '';
            echo "  \033[31m✗\033[0m {$label}{$msg}\n";
            $this->fail++;
            $this->failures[] = "[{$this->current_suite}] {$label}{$msg}";
        }
    }

    public function eq($expected, $actual, string $label): void {
        $detail = $expected !== $actual
            ? 'expected ' . json_encode($expected) . ', got ' . json_encode($actual)
            : '';
        $this->ok($expected === $actual, $label, $detail);
    }

    public function not_wp_error($value, string $label): bool {
        $ok = !is_wp_error($value);
        $detail = is_wp_error($value) ? $value->get_error_message() : '';
        $this->ok($ok, $label, $detail);
        return $ok;
    }

    public function is_wp_error_code($value, string $code, string $label): void {
        $ok = is_wp_error($value) && $value->get_error_code() === $code;
        $detail = is_wp_error($value) ? $value->get_error_code() : 'not a WP_Error';
        $this->ok($ok, $label, $detail);
    }

    public function summary(): void {
        $total = $this->pass + $this->fail;
        echo "\n\033[1m── Results: {$this->pass}/{$total} passed";
        if ($this->fail) {
            echo " — \033[31m{$this->fail} failed\033[0m\033[1m";
            echo "\033[0m\n";
            foreach ($this->failures as $f) {
                echo "   \033[31m• {$f}\033[0m\n";
            }
        } else {
            echo " \033[32m(all green)\033[0m\033[1m";
            echo "\033[0m\n";
        }
    }

    public function failed(): bool { return $this->fail > 0; }
}
