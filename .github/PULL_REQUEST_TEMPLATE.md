## Summary

<!-- Brief 1-3 sentence summary of the change, motivation, and context. -->

## Changes Proposed

- 
- 

## Type of Change

- [ ] `feat`: New feature or capability
- [ ] `fix`: Bug fix
- [ ] `refactor`: Code reorganization with no behavior change
- [ ] `perf`: Performance improvement
- [ ] `test`: Adding or updating tests
- [ ] `docs`: Documentation updates
- [ ] `chore`: Tooling, dependencies, or maintenance

## Verification Checklist

Please verify before requesting review:

- [ ] Ran `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify.ps1 fast` (or `bash scripts/verify.sh fast`) and it passed with zero errors.
- [ ] Ran `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify.ps1 full` (or `bash scripts/verify.sh full`) and it passed with zero errors.
- [ ] PHPStan reports 0 errors at `max` level.
- [ ] PHP-CS-Fixer reports no styling diffs (PSR-12 compliant).
- [ ] Every new PHP file begins with `declare(strict_types=1);`.
- [ ] All inputs validated; all SQL executed via PDO prepared statements.
- [ ] If changing API endpoints, `openapi/openapi.yaml` has been updated and passes Spectral lint.
- [ ] No secrets or sensitive configuration committed.
- [ ] Added or updated automated tests covering happy path, validation failures, and edge cases.
