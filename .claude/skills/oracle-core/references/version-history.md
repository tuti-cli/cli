# Version History

Recent Claude Code updates and feature additions.

## Version Tracking

This document tracks notable Claude Code updates. For the latest version, run:
```bash
claude --version
```

## Recent Features (2024-2025)

### Model Updates

- **Claude Opus 4.6** (`claude-opus-4-6`) - Latest flagship model
- **Claude Sonnet 4.6** (`claude-sonnet-4-6`) - Balanced performance
- **Claude Haiku 4.5** (`claude-haiku-4-5-20251001`) - Fast and efficient

### Fast Mode

- `--fast` flag enables faster output with the same model
- Does NOT switch to a different model
- Optimizes streaming and response generation

### Plugin System

- Plugins can be installed globally
- Structure: `~/.claude/plugin-name/`
- Enable in settings.json: `"enabledPlugins": { "name@source": true }`

### Skills and Commands

- Skills: Reusable workflows in `.claude/skills/`
- Commands: Slash commands in `.claude/commands/`
- Can be user-invocable or Claude-invoked

### MCP Integration

- Support for stdio, HTTP, and SSE transports
- Project-level (`.mcp.json`) and global (`~/.claude/mcp.json`) configs
- Debug mode: `--mcp-debug`

### Hooks

- PreToolUse, PostToolUse, Notification, Stop, PreCompact hooks
- Scripts in `.claude/hooks/<HookType>/`
- Can block or modify tool execution

### Agent SDK

- Custom agent definitions in markdown
- Tool access control
- Model selection per agent
- Subagent spawning via Task tool

### Memory

- Auto-memory in `~/.claude/projects/<project>/memory/`
- MEMORY.md loaded into system prompt
- Topic-specific memory files

### IDE Integration

- VS Code extension
- JetBrains plugin
- Inline suggestions and chat

## Key CLI Flags

| Flag | Purpose |
|------|---------|
| `--fast` | Faster output (same model) |
| `--continue` | Resume last session |
| `--resume <id>` | Resume specific session |
| `--dangerously-skip-permissions` | Auto-approve tools |
| `--print` | Non-interactive mode |
| `--mcp-debug` | MCP debug logging |

## Configuration Evolution

### Settings Structure

```json
{
  "model": "opus",
  "env": { ... },
  "enabledPlugins": { ... }
}
```

### Keybindings

Custom keybindings in `~/.claude/keybindings.json`:
- Chord support (`ctrl+k c`)
- Rebindable submit/newline keys

## Checking for Updates

### Method 1: NPM

```bash
npm update -g @anthropic-ai/claude-code
```

### Method 2: Check Version

```bash
claude --version
npm show @anthropic-ai/claude-code version
```

### Method 3: Oracle

```bash
/oracle updates
```

## Deprecation Notices

- Legacy MCP configuration formats may need migration
- Old hook formats updated to new structure

## Migration Guides

### Updating Hooks

If you have old-format hooks, migrate to:
```
.claude/hooks/<HookType>/hook-name.sh
```

### Updating MCP Config

Ensure MCP servers use the correct type field:
```json
{
  "mcpServers": {
    "name": {
      "type": "stdio",  // Required
      "command": "...",
      "args": [...]
    }
  }
}
```

## Feature Roadmap

For the latest roadmap and upcoming features:
- GitHub: https://github.com/anthropics/claude-code
- Docs: https://docs.anthropic.com/claude-code

## Reporting Issues

Found a bug or have a feature request?
- GitHub Issues: https://github.com/anthropics/claude-code/issues

---

*This document is updated periodically. Use `/oracle updates` to check for the latest changes.*
