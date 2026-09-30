## Summary
<!-- One or two sentences: what does this PR add or change? -->

**Module:** MOD-__  **Tasks:** <!-- e.g. CORE-07, CORE-09 -->
**Reviewer:** @

## Handoff pack
- **How it works / connects:** <!-- which includes, partials, tables and endpoints it touches -->
- **Setup to test:** <!-- branch, DB changes to import, seed data, config values -->
- **Test Plan cases to run:** <!-- e.g. TC-001 … TC-005 -->
- **Walkthrough video:** <!-- optional link, ~5 min -->

## Screenshots
<!-- Desktop and 375 px mobile, for any page change -->

## Author checklist
- [ ] Branched from the latest `develop` and merged `develop` in before opening
- [ ] Runs locally with no PHP warnings/errors
- [ ] Database changes are in `database/schema.sql` / `seed.sql`
- [ ] No secrets, `config.php`, uploads or model files committed
- [ ] Task Sheet updated (Status, Review, PR link)

## Reviewer checklist
- [ ] Runs locally on a fresh pull of the branch
- [ ] Assigned Test Plan cases pass (results logged)
- [ ] Security: prepared statements, escaped output (`htmlspecialchars`), CSRF on POST, access checks
- [ ] Readable, commented, follows coding conventions
- [ ] Works at 375 px width
- [ ] Comments are specific and constructive
