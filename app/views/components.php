<?php
declare(strict_types=1);

function url(string $route): string
{
    return './index.php?route=' . rawurlencode($route);
}

function route_url(string $route, array $params = []): string
{
    return './index.php?' . http_build_query(['route' => $route] + $params);
}

function status_label(string $status): string
{
    return ['pending' => 'Oczekująca', 'confirmed' => 'Potwierdzona', 'completed' => 'Zrealizowana', 'cancelled' => 'Anulowana'][$status] ?? $status;
}

function money_pl(mixed $value): string
{
    return $value === null ? 'Cena historyczna nieznana' : number_format((float) $value, 2, ',', ' ') . ' zł';
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function field(string $name, string $label, string $type, array $old, array $errors, string $autocomplete, int $max): void
{
    $secret = $type === 'password';
    $value = $secret ? '' : input($old, $name);
    ?>
    <div class="field">
        <label for="<?= e($name) ?>"><?= e($label) ?></label>
        <input id="<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>" value="<?= e($value) ?>"
            maxlength="<?= $max ?>" autocomplete="<?= e($autocomplete) ?>" required
            <?= isset($errors[$name]) ? 'aria-invalid="true" aria-describedby="' . e($name) . '-error"' : '' ?>>
        <?php if (isset($errors[$name])): ?><p class="field-error" id="<?= e($name) ?>-error"><?= e($errors[$name]) ?></p><?php endif ?>
    </div>
    <?php
}

function pin_field(string $name, string $label, array $errors): void
{
    ?>
    <div class="field pin-field" data-pin>
        <label for="<?= e($name) ?>" id="<?= e($name) ?>-label"><?= e($label) ?></label>
        <input id="<?= e($name) ?>" name="<?= e($name) ?>" type="password" inputmode="numeric"
            pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="off" required
            aria-describedby="<?= e($name) ?>-help<?= isset($errors[$name]) ? ' ' . e($name) . '-error' : '' ?>"
            <?= isset($errors[$name]) ? 'aria-invalid="true"' : '' ?>>
        <small class="muted" id="<?= e($name) ?>-help">6 cyfr. Stały PIN odzyskiwania, nie kod jednorazowy.</small>
        <?php if (isset($errors[$name])): ?><p class="field-error" id="<?= e($name) ?>-error"><?= e($errors[$name]) ?></p><?php endif ?>
    </div>
    <?php
}
