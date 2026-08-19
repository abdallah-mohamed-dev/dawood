<?php

test('pagination strings resolve to Arabic instead of falling through to the raw key', function () {
    expect(__('pagination.previous'))->toBe('السابق');
    expect(__('pagination.next'))->toBe('التالي');
    expect(__('Showing'))->toBe('عرض');
    expect(__('results'))->toBe('نتيجة');
});
