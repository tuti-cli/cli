# Best Practices Guide

Recommended patterns and practices for Claude Code usage.

## CLAUDE.md Best Practices

### Structure

```markdown
# Project Name

Brief description of the project.

## Architecture

Overview of the codebase structure and key components.

## Conventions

- Coding style guidelines
- Naming conventions
- File organization

## Development

- How to build/run/test
- Development workflow
- Deployment process

## Important Files

List of critical files and their purposes.

## Notes

Project-specific notes and gotchas.
```

### Guidelines

1. **Keep it concise** - Focus on what Claude needs to know
2. **Be specific** - Provide concrete examples and paths
3. **Update regularly** - Keep in sync with project changes
4. **Avoid duplication** - Don't repeat what's in other docs
5. **Use sections** - Organize with clear headings

### Example

```markdown
# My API Project

REST API using Express.js with PostgreSQL.

## Architecture

```
src/
├── routes/        # Express route handlers
├── services/      # Business logic
├── models/        # Database models
└── middleware/    # Express middleware
```

## Conventions

- Use camelCase for variables
- Use PascalCase for classes
- All routes return JSON
- Errors use { error: string, code: string } format

## Testing

Run tests with: `npm test`
Coverage: `npm run coverage`

## Important

- Never commit .env files
- All DB changes need migrations
- PRs require 2 approvals
```

## Hook Best Practices

### Organization

```
.claude/hooks/
├── PreToolUse/
│   └── validate-input.sh
├── PostToolUse/
│   └── format-code.sh
└── Notification/
    └── notify.sh
```

### Hook Guidelines

1. **Fast execution** - Hooks should complete quickly
2. **Clear feedback** - Provide actionable error messages
3. **Graceful failures** - Handle errors without breaking workflow
4. **Idempotent** - Running multiple times should be safe

### Example Hook: Pre-commit Check

```bash
#!/bin/bash
# .claude/hooks/PreToolUse/check-test-files.sh

# Check if Bash tool is being used to commit
if [[ "$TOOL_INPUT" == *"git commit"* ]]; then
  # Run tests before commit
  if ! npm test; then
    echo "Tests failed. Please fix before committing."
    exit 1
  fi
fi
```

### Example Hook: Auto-format

```bash
#!/bin/bash
# .claude/hooks/PostToolUse/format.sh

# Format modified JS/TS files
if [[ "$TOOL_NAME" == "Edit" || "$TOOL_NAME" == "Write" ]]; then
  FILE_PATH=$(echo "$TOOL_INPUT" | jq -r '.file_path // empty')
  if [[ "$FILE_PATH" == *.js || "$FILE_PATH" == *.ts ]]; then
    npx prettier --write "$FILE_PATH" 2>/dev/null
  fi
fi
```

## Skill Best Practices

### Structure

```markdown
---
description: Clear skill description
---

# Skill Name

Brief overview.

## When to Use

Specific triggers and use cases.

## Workflow

1. Step one
2. Step two
3. Step three

## Guidelines

Important considerations and constraints.
```

### Guidelines

1. **Clear triggers** - When should this skill be used
2. **Focused scope** - Do one thing well
3. **Reusable** - Work across projects
4. **Documented** - Include examples

### Example Skill

```markdown
---
description: Generate unit tests for functions
---

# Test Generator

Generate comprehensive unit tests for JavaScript/TypeScript functions.

## When to Use

- User asks to "add tests" or "write unit tests"
- New function needs test coverage
- Improving test coverage

## Workflow

1. Read the target function
2. Analyze inputs, outputs, edge cases
3. Generate test file with:
   - Normal cases
   - Edge cases
   - Error cases
4. Use appropriate testing framework (Jest, Vitest, etc.)

## Guidelines

- Use describe/it blocks
- Include meaningful test names
- Test edge cases explicitly
- Mock external dependencies
```

## MCP Server Best Practices

### Configuration

1. **Project-specific** - Use `.mcp.json` for project servers
2. **Global** - Use `~/.claude/mcp.json` for shared servers
3. **Environment** - Use env vars for secrets

### Server Design

1. **Single responsibility** - One server, one purpose
2. **Descriptive tools** - Clear names and descriptions
3. **Validation** - Validate all inputs
4. **Error handling** - Return meaningful errors
5. **Documentation** - Document all tools

### Security

1. **Never expose secrets** in tool descriptions
2. **Validate inputs** to prevent injection
3. **Rate limit** external API calls
4. **Log** sensitive operations

## Workflow Best Practices

### Project Setup

1. Create `CLAUDE.md` with project context
2. Configure relevant MCP servers
3. Set up useful hooks
4. Create project-specific skills

### Team Collaboration

1. **Version control** CLAUDE.md and .claude/ directory
2. **Document** custom tools and workflows
3. **Share** useful skills across team
4. **Standardize** hook behaviors

### Daily Usage

1. Start sessions with context: `/oracle` or relevant skill
2. Use memory (`#`) for important decisions
3. Review and update CLAUDE.md periodically
4. Clean up unused skills and hooks

## Code Quality

### What Claude Does Well

- Writing boilerplate code
- Refactoring for clarity
- Adding error handling
- Writing tests
- Documentation

### What to Review

- Security-sensitive code (auth, crypto)
- Business logic
- Performance-critical code
- External integrations

### Guidelines for Claude

1. **Be specific** about requirements
2. **Provide context** about the codebase
3. **Review changes** before accepting
4. **Test thoroughly** after modifications

## Performance Tips

1. **Use appropriate model** - Haiku for simple tasks
2. **Batch operations** - Combine related edits
3. **Limit context** - Don't include unnecessary files
4. **Use skills** - Pre-defined workflows are efficient

## Common Patterns

### Pattern: Feature Development

1. Plan in CLAUDE.md or skill
2. Use `/oracle` to understand current state
3. Implement in focused sessions
4. Add tests incrementally
5. Update documentation

### Pattern: Bug Fixing

1. Describe the bug clearly
2. Let Claude explore the codebase
3. Review proposed fix
4. Add regression test
5. Document the fix

### Pattern: Refactoring

1. Identify scope clearly
2. Ask Claude to explain current structure
3. Request step-by-step plan
4. Apply changes incrementally
5. Run tests after each step
