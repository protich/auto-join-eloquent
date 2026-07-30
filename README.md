# Auto Join Eloquent

**auto-join-eloquent** is a Laravel package that automates the process of joining related Eloquent models in your queries. By leveraging relationship metadata and centralized alias management, the package simplifies complex query building and ensures consistent `JOIN` clauses throughout your application.

This package was inspired by the need for dynamic queries in osTicket’s custom forms and fields. It was developed to address the challenges of handling dynamic and nested auto `JOIN` operations in such environments.

## Features

- **Automatic Join Processing**: Automatically joins related models based on defined Eloquent relationships without requiring manual `JOIN` clauses.
- **Nested Relationships**: Supports deep nesting of relationships using dot (`.`) or double-underscore (`__`) notation.
- **Aggregate Functions**: Seamlessly compile aggregates in `SELECT` and `HAVING` clauses with built-in support for functions such as `COUNT`, `SUM`, `AVG`, `MIN`, and `MAX`.

### Aliasing Management

- **Custom Aliases**: Define custom join aliases directly in your models via a `$joinAliases` property.
- **Auto Generated Aliasing**: Optionally, the package can automatically generate simple sequential aliases (`A`, `B`, `C`, …, then `A1`, `B1`, etc.) or descriptive aliases based on relationship keys.

- **Raw SQL Handling**: Supports raw `HAVING` clauses and can intelligently compile aggregate expressions when raw SQL references relationships.

### Clause-Specific Compilation

- **SelectCompiler**: Processes `SELECT` clause columns (adds aliases and handles aggregates).
- **WhereCompiler**: Transforms `WHERE` clause columns into fully qualified column names (without aliasing).
- **HavingCompiler**: Compiles `HAVING` clause expressions (handles aggregates without aliasing).
- **OrderByCompiler**: Compiles `ORDER BY` clause expressions, ensuring that the sorting columns are properly resolved.
- **GroupByCompiler**: Compiles `GROUP BY` clause expressions, guaranteeing that grouping columns are correctly qualified with their respective aliases.

## Installation

Install via Composer:

```bash
composer require protich/auto-join-eloquent
```

### Requirements

- PHP 8.3
- Illuminate Database 13

Version 0.11 supports `BelongsTo`, `HasOne`, `HasMany`, and
`BelongsToMany` relationships. Other Eloquent relationship types, including
`HasOneThrough` and `HasManyThrough`, fail with an explicit unsupported-type
exception before join compilation.

## Usage

### Enabling Auto Join

Include the `AutoJoinTrait` in your Eloquent models to enable auto-join functionality. For example, in your `User` model:

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use protich\AutoJoinEloquent\Traits\AutoJoinTrait;
class User extends Model {
    use AutoJoinTrait;

    // Define relationships normally.
    public function agent() {
        return $this->hasOne(Agent::class, 'user_id');
    }
}
```

### Custom Join Aliases

To define custom join aliases, add a `$joinAliases` property to your model. For example, create a `UserStaff` model to force the agent relationship to use the alias "staff":

```php
<?php
namespace App\Models;
use protich\AutoJoinEloquent\Traits\AutoJoinTrait;
use Illuminate\Database\Eloquent\Model;
class UserStaff extends User {
    use AutoJoinTrait;

    protected $table = 'users';
    public $timestamps = false;

    // Define a custom alias for the "agent" relationship.
    public $joinAliases = [
        'agent' => 'staff'
    ];

    public function agent() {
        return $this->hasOne(Agent::class, 'user_id');
    }
}
```

### Query Examples

#### Basic Auto-Joined Query

auto-join-eloquent allows you to write clean queries that automatically apply joins based on relationship notation. For example, a basic query with an aggregate and a WHERE condition might look like:

```php
<?php
$results = User::query()
    ->select([
        'name as user_name',
        'agent.id as agent_id',
        'COUNT(agent.departments.id) as dept_count'
    ])
    ->where('agent.id', '=', 1)
    ->groupBy('agent.id')
    ->havingRaw('COUNT(agent.departments.id) > ?', [0])
    ->orderBy('name', 'asc')
    ->get();
```

In this example, the package automatically joins the related `agent` model and aggregates data from the nested `departments` relationship. The HAVING clause compiles the raw SQL aggregate as needed, ensuring proper filtering. Additionally, ORDER BY and GROUP BY clauses are compiled to ensure proper aliasing and relationship resolution.

#### Advanced Raw HAVING Example

You can also use raw expressions for HAVING clauses that reference relationships. For instance:

```php
<?php
$results = User::query()
    ->select(['name as user_name'])
    ->groupBy('agent.id')
    ->havingRaw('COUNT(agent.departments.id) > ?', [2])
    ->get();
```

This query compiles the raw SQL and correctly applies the aggregate condition.

### Model-Defined Paths

Normal fields and Eloquent relationships are resolved first. When the package
reaches a path segment it cannot resolve, it asks the model at that hop to
describe the complete unresolved remainder. A model returns `null` when it
does not own that expression, preserving the package's permissive handling of
unknown columns and literal expressions.

```php
use protich\AutoJoinEloquent\Model\ExpressionDescriptor;
use protich\AutoJoinEloquent\Model\PathRequest;

public static function describeAutoJoinPath(
    PathRequest $request
): ?ExpressionDescriptor {
    return match ($request->path) {
        'status' => ExpressionDescriptor::path('flags'),

        'accessibleDepartments__id__count' =>
            ExpressionDescriptor::count([
                'departments.id',
                'groups.departments.id',
            ], distinct: true),

        default => null,
    };
}
```

The returned `ExpressionDescriptor` tells the package how to compile the
logical expression. For example,
`organization__cdata__field_id__42` traverses `organization` and `cdata`
normally, then offers only `field_id__42` to the CData model.

The optional `model__` marker remains supported as an explicit compatibility
escape hatch. It asks the base model first and, when declined, follows the same
hop-local fallback behavior.

### Complex Relationships

Standard Eloquent relationship metadata is resolved automatically. The package
asks the owning model to resolve every named relationship and return an
`AutoJoinRelation`. Relationships containing additional query constraints must
describe those constraints:

```php
use protich\AutoJoinEloquent\Model\AutoJoinRelation;

public function activeAssignments()
{
    return $this->hasMany(Assignment::class)
        ->where('assignments.status', 'active');
}

public function describeAutoJoinRelation(
    string $name,
    string $path
): AutoJoinRelation {
    $description = parent::describeAutoJoinRelation($name, $path);

    return match ($name) {
        'activeAssignments' => $description
            ->whereRelated('status', 'active'),
        default => $description,
    };
}
```

`AutoJoinRelation` supports constraints targeting the related, parent, or pivot
table through `whereRelated()`, `whereParent()`, and `wherePivot()`, with
corresponding null helpers. Constraint values are added to the join as query
bindings.

The base implementation verifies the named relationship, resolves it, and
creates an empty description. The auto-joiner asks the model for this
description for every relationship. `JoinComplexity` determines whether an
empty result is valid: complex relationships must include every row-affecting
condition that is not part of standard Eloquent relationship key metadata.

The package does not attempt to prove that a description is equivalent to
arbitrary query-builder state. If a relationship uses a condition that
`AutoJoinRelation` cannot express, the model hook should throw rather than
provide a partial description.

## Upgrading

Version 0.11 adds implicit, hop-local model expression resolution and makes
the model hook nullable. Applications upgrading from earlier versions should
follow [UPGRADING.md](UPGRADING.md).

## Configuration

You can configure default behavior via the package configuration file (if published):

- **use_simple_aliases:** Enable or disable simple sequential alias generation.
- **join_type:** Set the default join type (e.g., left or inner).

## Running Tests

The package includes a comprehensive test suite built using Orchestra Testbench and an in‑memory SQLite database along with migrations and seeders. This setup simulates a full Laravel environment while keeping tests fast and isolated. It ensures that every part of the auto-join functionality is thoroughly verified—from query compilation to relationship aliasing and clause-specific processing.

You can run the test suite using one of the following methods:

- **Using Composer script**
  Run all tests with:
```bash
  composer test
```

- **Using Composer with a filter**
  To run only tests matching a specific filter (e.g., tests containing “Basic”), use:
```bash
  composer test -- --filter=Basic
```

- **Directly using PHPUnit**
  Alternatively, run PHPUnit directly:
```bash
  ./vendor/bin/phpunit
```

The model-facing descriptor and relationship-complexity API has a focused
maximum-strictness PHPStan check:

```bash
composer phpstan:model-api
```

## Internal Architecture

- **AutoJoinQueryBuilder:**
  Extends Laravel's Eloquent Builder to intercept queries and apply auto-join processing. It delegates alias resolution to a dedicated alias manager.

- **JoinAliasManager:**
  Centralizes join alias resolution by generating sequential aliases (or using forced custom aliases defined in models) and preventing collisions. All alias mapping logic is managed in this component.

- **Join Helpers:**
  Classes such as `JoinClauseInfo`, `JoinContext`, and `JoinComplexity` encapsulate join metadata, context, and complex-relation detection.

- **Model API:**
  `PathRequest`, `ExpressionDescriptor`, and `AutoJoinRelation` provide the
  small public API used by model hooks. These classes live under the
  `protich\AutoJoinEloquent\Model` namespace.

- **Compilers:**
  Components like `SelectCompiler`, `WhereCompiler`, `HavingCompiler`, `OrderByCompiler`, and `GroupByCompiler` compile various parts of the query by interpreting relationship chains and applying alias logic.

- **AutoJoinTrait:**
  A trait you include in your models to enable auto-join behavior and hook into the query builder.

## Known Limitations & TODOs

- **Manual vs. Auto Joins:** The package assumes that auto-join is the primary mechanism for adding JOIN clauses. Future versions may reconcile manual JOIN clauses with auto-joins.
- **Raw SQL in HAVING Clauses:** While the package supports raw HAVING expressions, further enhancements may be needed to robustly compile complex raw SQL.
- **Alias Collision Handling:** Currently, if a forced custom alias is already in use, the system falls back to auto-generation. Future enhancements might include throwing an exception or logging warnings.
- **Join Reconciliation:** Reconciling manually added joins with auto-joins is a potential future enhancement.

## Contributing

Contributions are welcome! Please fork the repository and submit pull requests. Follow the coding standards and include tests for any new features or bug fixes.

## License

This package is open-source software licensed under the [MIT License](LICENSE).

For more details, visit the project repository:
[https://github.com/protich/auto-join-eloquent](https://github.com/protich/auto-join-eloquent)
