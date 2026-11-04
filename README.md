# Restriction by quiz attempted (availability_attempted)

A restrict access condition for Moodle: an activity or section stays locked until the student has **submitted an
attempt at a chosen quiz, whatever their grade**.

Moodle's own *Activity completion* condition can't do this when a quiz's completion requires a passing grade: a
student who attempted and failed hasn't "completed" it. This condition only asks whether they tried.

## Use

In an activity's or section's settings, **Restrict access → Add restriction… → Quiz attempted**, then pick a quiz, or
**Previous quiz** (the last quiz before this item on the course page, handy for chaining a unit). Students see, for
example, "Not available unless: An attempt at **Quiz 1** has been submitted". Set the condition group to "must not"
for the opposite ("No attempt at … has been submitted").

| Attempt | Counts? |
|---|---|
| Submitted, passed or failed | Yes |
| Submitted, waiting for manual grading | Yes |
| Started but not submitted | No |
| Never submitted before time ran out (abandoned) | No |
| Teacher preview | No |

The condition is offered only in courses that have a quiz. It survives backup and restore and activity duplication
like the core conditions.

## Requirements

Moodle 5.2 or 5.3.

## License

GNU GPL v3 or later. Copyright 2026 misko92.
