<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Helpers;

/**
 * A stupidly-simple class to aid with building paths.
 * Designed for web routes first and foremost, though there's
 * nothing stopping you from using it for filesystem paths.
 */
class Path
{
    public function __construct(protected string $base = '', protected string $separator = '/')
    {
    }

    /**
     * Append to the path, preventing extraneous path separators.
     *
     * Example:
     * ```
     * $path = (new Path('http://localhost'))->join('index.php'); // $path is now: http://localhost/index.php
     * ```
     * @param array|string $route
     * @return $this
     */
    public function join(array|string $route): static
    {
        if (is_array($route)) {
            foreach ($route as $item) {
                $this->join($item);
            }

            return $this;
        }

        $base = empty($this->base) ? '' : rtrim($this->base, $this->separator) . $this->separator;
        $this->base = $base . ltrim($route, $this->separator);
        return $this;
    }

    /**
     * Returns a new copy of the path. Use this when you do not want to modify the original object.
     *
     * Example:
     * ```
     * $base = new Path('/home/my-user');
     * $dir = $base->copy()->join('Documents');
     *
     * printf("Home dir: %s\n", $base);
     * printf("Doc root: %s\n", $dir);
     * ```
     * @return $this
     */
    public function copy(): static
    {
        return new static($this->base, $this->separator);
    }

    public function toString(): string
    {
        return $this->base;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}