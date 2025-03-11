<?php


use USSoccerFederation\UssfAuthSdkPhp\Helpers\Path;

test('can join paths', function () {
    $path = (new Path('http://localhost:8080'))
        ->join('api')
        ->join('v1')
        ->join('hello')
        ->join('world');

    expect($path->toString())->toBe('http://localhost:8080/api/v1/hello/world');
});

test('can join paths as array', function () {
    $path = (new Path('http://localhost'))
        ->join(['1', '2','3','4', '5']);

    expect($path->toString())->toBe('http://localhost/1/2/3/4/5');
});

test('does not include separator on empty segment', function () {
    $path = (new Path())->join('1/2/3');
    expect($path->toString())->toBe('1/2/3');

    $base = new Path('/home/my-user');
    $dir = $base->copy()->join('Documents');
});

test('can use alternate separator', function () {
    // todo: insert snide remark about Windows here
    $path = (new Path('C:', '\\'))
        ->join('Windows')
        ->join('system32');

    expect($path->toString())->toBe('C:\\Windows\\system32');
});

test('can copy', function () {
    $original = new Path('hello');
    $new = $original->copy();
    $new->join('world');

    expect($original->toString())->toBe('hello');
    expect($new->toString())->toBe('hello/world');
});

test('can trim unnecessary separators', function () {
    $path = (new Path('1/'))->join('/2//')->join('///3/');

    expect($path->toString())->toBe('1/2/3/');
});

test('can cast to string', function () {
    $path = new Path('hello/world');
    $autoCast = function (string $input) {
        return $input;
    };

    expect((string)$path)->toBe('hello/world')
        ->and($autoCast($path))->toBe('hello/world');
});