<?php

declare(strict_types=1);

namespace App\Domain\Academic\Exceptions;

use RuntimeException;

/**
 * A catalog row cannot be deleted because another record still references it
 * (a restrict FK). Carrying only a translated message keeps the Academic domain
 * free of any Illuminate\Http dependency — the HTTP render (302 + field error
 * on web, 422 JSON on API) lives in bootstrap/app.php beside the
 * InvalidStatusTransitionException handler. Thrown by the Delete Actions as an
 * isReferenced() pre-check — an in-use row is refused before any mutation, so the
 * restrict FK is never tripped (the soft-delete catalogs never reach a SQL DELETE).
 */
final class CatalogInUseException extends RuntimeException {}
