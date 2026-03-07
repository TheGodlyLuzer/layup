<?php

declare(strict_types=1);

use Crumbls\Layup\Support\WidgetContext;
use Crumbls\Layup\Support\WidgetRegistry;
use Crumbls\Layup\View\HeadingWidget;
use Crumbls\Layup\View\TextWidget;

beforeEach(function (): void {
    $this->registry = new WidgetRegistry;
});

it('registers a widget and retrieves by type', function (): void {
    $this->registry->register(TextWidget::class);
    expect($this->registry->get('text'))->toBe(TextWidget::class);
});

it('has() returns true for registered and false for unregistered', function (): void {
    $this->registry->register(TextWidget::class);
    expect($this->registry->has('text'))->toBeTrue();
    expect($this->registry->has('nonexistent'))->toBeFalse();
});

it('unregister() removes a widget', function (): void {
    $this->registry->register(TextWidget::class);
    $this->registry->unregister('text');
    expect($this->registry->has('text'))->toBeFalse();
});

it('all() returns all registered widgets', function (): void {
    $this->registry->register(TextWidget::class);
    $this->registry->register(HeadingWidget::class);
    expect($this->registry->all())->toHaveCount(2)
        ->toHaveKeys(['text', 'heading']);
});

it('toJs() returns correct array structure', function (): void {
    $this->registry->register(TextWidget::class);
    $js = $this->registry->toJs();
    expect($js)->toBeArray()->toHaveCount(1);
    expect($js[0])->toHaveKeys(['type', 'label', 'icon', 'category', 'defaults']);
    expect($js[0]['type'])->toBe('text');
});

it('grouped() groups by category', function (): void {
    $this->registry->register(TextWidget::class);
    $this->registry->register(HeadingWidget::class);
    $grouped = $this->registry->grouped();
    expect($grouped)->toHaveKey('content');
    expect($grouped['content'])->toHaveCount(2);
});

it('throws InvalidArgumentException for non-Widget class', function (): void {
    $this->registry->register(\stdClass::class);
})->throws(\InvalidArgumentException::class);

it('getFormSchema() returns array for known type', function (): void {
    $this->registry->register(TextWidget::class);
    expect($this->registry->getFormSchema('text'))->toBeArray()->not->toBeEmpty();
});

it('getFormSchema() returns empty array for unknown type', function (): void {
    expect($this->registry->getFormSchema('unknown'))->toBe([]);
});

it('getDefaultData() returns defaults', function (): void {
    $this->registry->register(TextWidget::class);
    expect($this->registry->getDefaultData('text'))->toBeArray()->toHaveKey('content');
});

it('getPreview() returns preview text', function (): void {
    $this->registry->register(TextWidget::class);
    expect($this->registry->getPreview('text', ['content' => 'Hello']))->toBe('Hello');
});

it('fireOnCreate() calls onCreate', function (): void {
    $this->registry->register(TextWidget::class);
    $result = $this->registry->fireOnCreate('text', ['content' => 'test']);
    expect($result)->toBeArray()->toHaveKey('content');
});

it('fireOnSave() calls onSave', function (): void {
    $this->registry->register(TextWidget::class);
    $result = $this->registry->fireOnSave('text', ['content' => 'test']);
    expect($result)->toBeArray();
});

it('fireOnDelete() calls onDelete without error', function (): void {
    $this->registry->register(TextWidget::class);
    $this->registry->fireOnDelete('text', ['content' => 'test']);
    expect(true)->toBeTrue(); // no exception
});

it('fireOnSave() accepts optional WidgetContext', function (): void {
    $this->registry->register(TextWidget::class);
    $ctx = new WidgetContext(null, 'r1', 'c1', 'w1');
    $result = $this->registry->fireOnSave('text', ['content' => 'hi'], $ctx);
    expect($result)->toBeArray()->toHaveKey('content');
});

it('fireOnCreate() accepts optional WidgetContext', function (): void {
    $this->registry->register(TextWidget::class);
    $ctx = new WidgetContext(null, 'r1', 'c1', 'w1');
    $result = $this->registry->fireOnCreate('text', ['content' => 'hi'], $ctx);
    expect($result)->toBeArray();
});

it('fireOnDelete() accepts optional WidgetContext', function (): void {
    $this->registry->register(TextWidget::class);
    $ctx = new WidgetContext(null, 'r1', 'c1', 'w1');
    $this->registry->fireOnDelete('text', ['content' => 'hi'], $ctx);
    expect(true)->toBeTrue();
});

it('getFingerprint() returns consistent hash for same widgets', function (): void {
    $this->registry->register(TextWidget::class);
    $this->registry->register(HeadingWidget::class);
    $fp1 = $this->registry->getFingerprint();
    $fp2 = $this->registry->getFingerprint();
    expect($fp1)->toBe($fp2)->toBeString()->toHaveLength(32);
});

it('getFingerprint() changes when widgets are added', function (): void {
    $this->registry->register(TextWidget::class);
    $fp1 = $this->registry->getFingerprint();
    $this->registry->register(HeadingWidget::class);
    $fp2 = $this->registry->getFingerprint();
    expect($fp1)->not->toBe($fp2);
});

it('getFingerprint() changes when widgets are removed', function (): void {
    $this->registry->register(TextWidget::class);
    $this->registry->register(HeadingWidget::class);
    $fp1 = $this->registry->getFingerprint();
    $this->registry->unregister('heading');
    $fp2 = $this->registry->getFingerprint();
    expect($fp1)->not->toBe($fp2);
});

it('getFingerprint() is order-independent', function (): void {
    $reg1 = new WidgetRegistry;
    $reg1->register(TextWidget::class);
    $reg1->register(HeadingWidget::class);

    $reg2 = new WidgetRegistry;
    $reg2->register(HeadingWidget::class);
    $reg2->register(TextWidget::class);

    expect($reg1->getFingerprint())->toBe($reg2->getFingerprint());
});

it('toJs() returns cached results on repeated calls', function (): void {
    $this->registry->register(TextWidget::class);
    $first = $this->registry->toJs();
    $second = $this->registry->toJs();
    expect($first)->toBe($second);
});

it('grouped() returns cached results on repeated calls', function (): void {
    $this->registry->register(TextWidget::class);
    $this->registry->register(HeadingWidget::class);
    $first = $this->registry->grouped();
    $second = $this->registry->grouped();
    expect($first)->toBe($second);
});

it('clearCache() resets fingerprint', function (): void {
    $this->registry->register(TextWidget::class);
    $fp1 = $this->registry->getFingerprint();
    $this->registry->clearCache();
    $fp2 = $this->registry->getFingerprint();
    // Same widgets, so fingerprint regenerates to the same value
    expect($fp1)->toBe($fp2);
});
