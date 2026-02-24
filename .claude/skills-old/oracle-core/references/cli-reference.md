# CLI Reference

Complete reference for Claude Code CLI commands, flags, and shortcuts.

## Commands

### Main Commands

```bash
claude                      # Start interactive session
claude "prompt"             # Start with initial prompt
claude -p "prompt"          # Print mode (non-interactive)
claude --continue           # Continue most recent session
claude --resume <id>        # Resume specific session by ID
claude --print              # Print mode output
claude --help               # Show help
claude --version            # Show version
```

### Session Management

```bash
claude --resume             # Interactive session picker
claude --resume abc123      # Resume session with ID abc123
claude --continue           # Continue last session in current dir
```

## Flags

### Model Selection

```bash
--model, -m <model>         # Specify model (opus, sonnet, haiku)
--opus                      # Use Claude Opus 4.6
--sonnet                    # Use Claude Sonnet 4.6
--haiku                     # Use Claude Haiku 4.5
--fast                      # Use faster output mode (same model)
```

### Execution Mode

```bash
-p, --print                 # Print mode (non-interactive, output to stdout)
--continue                  # Continue previous session
--resume <id>               # Resume specific session
--no-context                # Start fresh without previous context
```

### Permissions

```bash
--dangerously-skip-permissions    # Auto-approve all tool calls (use with caution)
--allowedTools <tools>            # Pre-approve specific tools (comma-separated)
```

### Output Control

```bash
--output-format <format>    # Output format (text, json, stream-json)
--verbose                   # Verbose output
--quiet, -q                 # Minimal output
```

### Configuration

```bash
--config <path>             # Use custom config directory
--settings <path>           # Use custom settings file
```

### MCP

```bash
--mcp-config <path>         # Use custom MCP config file
--mcp-debug                 # Enable MCP debug logging
```

## Keyboard Shortcuts

### In Interactive Mode

| Shortcut | Action |
|----------|--------|
| `Ctrl+C` | Cancel current action/input |
| `Ctrl+D` | Exit Claude Code |
| `Ctrl+L` | Clear screen |
| `↑` / `↓` | Navigate command history |
| `Tab` | Autocomplete |
| `Enter` | Submit message |

### Special Prefixes

| Prefix | Action |
|--------|--------|
| `/` | Invoke slash command or skill |
| `!` | Run shell command |
| `#` | Add memory to CLAUDE.md |
| `@` | Mention/attach file or directory |

## Environment Variables

### API Configuration

```bash
ANTHROPIC_API_KEY           # API key (or use ANTHROPIC_AUTH_TOKEN)
ANTHROPIC_AUTH_TOKEN        # Alternative auth token
ANTHROPIC_BASE_URL          # Custom API endpoint
API_TIMEOUT_MS              # API timeout in milliseconds
```

### Model Configuration

```bash
ANTHROPIC_DEFAULT_OPUS_MODEL    # Default Opus model
ANTHROPIC_DEFAULT_SONNET_MODEL  # Default Sonnet model
ANTHROPIC_DEFAULT_HAIKU_MODEL   # Default Haiku model
```

### Debug

```bash
CLAUDE_DEBUG                # Enable debug logging
CLAUDE_LOG_LEVEL            # Log level (debug, info, warn, error)
```

## Configuration Files

### Settings (`~/.claude/settings.json`)

```json
{
  "model": "opus",
  "env": {
    "ANTHROPIC_API_KEY": "your-key"
  },
  "enabledPlugins": {
    "plugin-name@source": true
  }
}
```

### Keybindings (`~/.claude/keybindings.json`)

```json
{
  "submit": ["enter"],
  "newLine": ["alt+enter", "shift+enter"],
  "chords": {
    "ctrl+k c": "copy",
    "ctrl+k v": "paste"
  }
}
```

### MCP Config (`.mcp.json` or `~/.claude/mcp.json`)

```json
{
  "mcpServers": {
    "server-name": {
      "type": "stdio",
      "command": "node",
      "args": ["path/to/server.js"]
    }
  }
}
```

## Session IDs

Sessions are identified by unique IDs. Find them via:
- `claude --resume` (interactive picker)
- Session files in `~/.claude/sessions/`
- Session metadata in conversation

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | General error |
| 2 | Invalid arguments |
| 130 | Interrupted (Ctrl+C) |

## Tips

1. **Faster Responses**: Use `--fast` for faster output with the same model
2. **Scripting**: Use `-p` and `--output-format json` for scripting
3. **Auto-approve**: Use `--dangerously-skip-permissions` cautiously in trusted environments
4. **Resume**: Use `--continue` for quick continuation, `--resume` for specific sessions
