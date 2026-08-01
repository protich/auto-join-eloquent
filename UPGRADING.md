# Upgrading Auto Join Eloquent

## From 0.10 to 0.11

Version 0.11 resolves normal columns and relationships before asking the model
at the first unresolved hop to describe the remaining path. Update the package
constraint in consuming applications:

```json
{
  "require": {
    "protich/auto-join-eloquent": "^0.11.0"
  }
}
```

### Return null for expressions the model does not own

The model hook is now nullable. Replace exceptions used only to decline an
unknown expression with `null`:

```php
public static function describeAutoJoinPath(
    PathRequest $request
): ?ExpressionDescriptor {
    return match ($request->path) {
        'status' => ExpressionDescriptor::path('flags'),
        default => null,
    };
}
```

Validation failures for expressions the model does recognize should still
throw an exception.

### Describe only the unresolved local remainder

Normal relationships no longer need to be repeated inside a base model's
descriptor. Given:

```text
organization__cdata__field_id__42
```

the package traverses `organization` and `cdata`, then calls the CData model
with `PathRequest('field_id__42')`. Its descriptor is relative to that model:

```php
return ExpressionDescriptor::path('values.field_value');
```

The package rebases the returned path onto the relationships already resolved.
The `model__` prefix remains supported for compatibility and explicit
delegation.

## From 0.9 to 0.10

Version 0.10 targets PHP 8.3 and Illuminate Database 13. Update the package
constraint in consuming applications:

```json
{
  "require": {
    "protich/auto-join-eloquent": "^0.10.0"
  }
}
```

### Describe complete model-defined paths

The package no longer splits `model__` expressions before asking the model to
describe them. Replace the string-and-remainder hook:

```php
public static function describeAutoJoinPath(
    string $path,
    array $remainder
): array;
```

with the typed API:

```php
use protich\AutoJoinEloquent\Model\ExpressionDescriptor;
use protich\AutoJoinEloquent\Model\PathRequest;

public static function describeAutoJoinPath(
    PathRequest $request
): ExpressionDescriptor {
    return match ($request->path) {
        'status' => ExpressionDescriptor::path('flags'),
        default => throw new \RuntimeException(sprintf(
            'Unsupported auto-join path [%s].',
            $request->path
        )),
    };
}
```

`protich\AutoJoinEloquent\Support\Descriptor` has been removed. Use
`protich\AutoJoinEloquent\Model\ExpressionDescriptor` and its `path()` or
`count()` factories instead.

### Resolve relationships through the model

Models using auto-join behavior must expose the relationship-description hook:

```php
use protich\AutoJoinEloquent\Model\AutoJoinRelation;

public function describeAutoJoinRelation(
    string $name,
    string $path
): AutoJoinRelation {
    $relation = parent::describeAutoJoinRelation($name, $path);

    return match ($name) {
        'activeAssignments' => $relation
            ->whereRelated('status', 'active'),
        default => $relation,
    };
}
```

The base implementation resolves the named Eloquent relationship and returns
an empty `AutoJoinRelation`. Override the hook only to add constraints that
cannot be derived from standard relationship key metadata.

The auto-joiner calls the hook for normal and complex relationships.
`JoinComplexity` only determines whether an empty description is acceptable.

### Supported relationship types

Version 0.10 supports:

- `BelongsTo`
- `HasOne`
- `HasMany`
- `BelongsToMany`

`HasOneThrough`, `HasManyThrough`, and other relationship types are not yet
supported. They throw an `InvalidArgumentException` before join compilation.

### Builder path inspection

`AutoJoinQueryBuilder::parseModelDefinedPath()` has been removed. Use
`describeModelDefinedPath()` when inspecting a model-defined expression. It
returns the complete path and the typed `ExpressionDescriptor`.
