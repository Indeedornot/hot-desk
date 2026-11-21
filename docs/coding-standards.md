# Coding standards

PHP uses strict types and the Symfony ruleset enforced by PHP-CS-Fixer.
Angular uses strict TypeScript, standalone components, typed HTTP responses
and Prettier. Use two spaces for frontend/configuration files and four for PHP.
Use braces for every TypeScript conditional branch, even a single return.
Use straight apostrophes and let Prettier wrap templates and expressions.
Use matching `.component.ts` and `.component.html` names. Keep components and
feature-specific services in the same feature folder. Declare class members with
explicit visibility. Component fields and methods used in templates are public.

Controllers handle HTTP mapping. Services enforce business rules and transaction
boundaries. Repositories contain database queries. Request DTOs validate input;
response mappers select public fields explicitly.

Name methods for the operation they perform. Comment only decisions that are
not evident from the code. Keep credentials in local environment files.
Preserve booking and audit history; never log passwords or session tokens.

Run the `format` VS Code task before the `test` task. Add regression tests for
business-rule changes and test authorization through the API.
