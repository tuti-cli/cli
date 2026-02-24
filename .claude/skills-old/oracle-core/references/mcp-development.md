# MCP Development Guide

Comprehensive guide to developing MCP (Model Context Protocol) servers for Claude Code.

## Overview

MCP (Model Context Protocol) allows Claude Code to connect to external tools, APIs, and data sources. MCP servers act as bridges between Claude and external systems.

## Server Types

### 1. stdio (Standard Input/Output)

Most common type. Communication via stdin/stdout.

```json
{
  "mcpServers": {
    "my-server": {
      "type": "stdio",
      "command": "node",
      "args": ["server.js"],
      "env": {
        "API_KEY": "your-key"
      }
    }
  }
}
```

### 2. HTTP

HTTP-based communication.

```json
{
  "mcpServers": {
    "my-http-server": {
      "type": "http",
      "url": "http://localhost:3000/mcp"
    }
  }
}
```

### 3. SSE (Server-Sent Events)

Event-based streaming.

```json
{
  "mcpServers": {
    "my-sse-server": {
      "type": "sse",
      "url": "http://localhost:3000/sse"
    }
  }
}
```

## Server Structure

### Using TypeScript/Node.js

```typescript
import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';

const server = new Server({
  name: 'my-server',
  version: '1.0.0',
}, {
  capabilities: {
    tools: {},
    resources: {},
  },
});

// Register tools
server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: [
    {
      name: 'my_tool',
      description: 'Tool description',
      inputSchema: {
        type: 'object',
        properties: {
          param: { type: 'string', description: 'Parameter description' }
        },
        required: ['param']
      }
    }
  ]
}));

// Handle tool calls
server.setRequestHandler(CallToolRequestSchema, async (request) => {
  if (request.params.name === 'my_tool') {
    const { param } = request.params.arguments;
    // Process and return
    return {
      content: [{ type: 'text', text: `Result: ${param}` }]
    };
  }
});

// Start server
const transport = new StdioServerTransport();
await server.connect(transport);
```

### Using Python

```python
from mcp.server import Server
from mcp.server.stdio import stdio_server

server = Server("my-server")

@server.list_tools()
async def list_tools():
    return [
        {
            "name": "my_tool",
            "description": "Tool description",
            "inputSchema": {
                "type": "object",
                "properties": {
                    "param": {"type": "string", "description": "Parameter description"}
                },
                "required": ["param"]
            }
        }
    ]

@server.call_tool()
async def call_tool(name: str, arguments: dict):
    if name == "my_tool":
        param = arguments["param"]
        return [{"type": "text", "text": f"Result: {param}"}]

async def main():
    async with stdio_server() as (read_stream, write_stream):
        await server.run(read_stream, write_stream)
```

## Tools

### Defining Tools

```typescript
{
  name: 'search_docs',
  description: 'Search documentation for relevant information',
  inputSchema: {
    type: 'object',
    properties: {
      query: {
        type: 'string',
        description: 'Search query'
      },
      limit: {
        type: 'number',
        description: 'Maximum results',
        default: 10
      }
    },
    required: ['query']
  }
}
```

### Tool Response Format

```typescript
// Text response
return {
  content: [
    { type: 'text', text: 'Plain text response' }
  ]
};

// Image response
return {
  content: [
    {
      type: 'image',
      data: base64EncodedImage,
      mimeType: 'image/png'
    }
  ]
};

// Multiple content items
return {
  content: [
    { type: 'text', text: 'Here is the image:' },
    { type: 'image', data: base64Data, mimeType: 'image/png' }
  ]
};

// Error response
return {
  content: [
    { type: 'text', text: 'Error: Something went wrong' }
  ],
  isError: true
};
```

## Resources

Resources provide read-only access to data.

### Defining Resources

```typescript
@server.list_resources()
async def list_resources():
    return [
        {
            "uri": "file:///path/to/file",
            "name": "My File",
            "description": "Description of the file",
            "mimeType": "text/plain"
        }
    ]

@server.read_resource()
async def read_resource(uri: str):
    if uri == "file:///path/to/file":
        return "File contents here"
    raise ValueError(f"Unknown resource: {uri}")
```

## Prompts

Pre-defined prompt templates.

```typescript
@server.list_prompts()
async def list_prompts():
    return [
        {
            "name": "code_review",
            "description": "Review code for issues",
            "arguments": [
                {
                    "name": "language",
                    "description": "Programming language",
                    "required": True
                }
            ]
        }
    ]

@server.get_prompt()
async def get_prompt(name: str, arguments: dict):
    if name == "code_review":
        return {
            "messages": [
                {
                    "role": "user",
                    "content": f"Review this {arguments['language']} code for best practices..."
                }
            ]
        }
```

## Best Practices

### 1. Error Handling

```typescript
try {
  const result = await riskyOperation();
  return { content: [{ type: 'text', text: result }] };
} catch (error) {
  return {
    content: [{ type: 'text', text: `Error: ${error.message}` }],
    isError: true
  };
}
```

### 2. Input Validation

```typescript
if (!arguments.param || typeof arguments.param !== 'string') {
  return {
    content: [{ type: 'text', text: 'Invalid parameter: param must be a string' }],
    isError: true
  };
}
```

### 3. Descriptive Messages

```typescript
// Good
description: 'Search the documentation database. Returns up to 10 most relevant documents based on semantic similarity.'

// Bad
description: 'Search docs'
```

### 4. Security

- Never expose API keys in tool descriptions or responses
- Validate and sanitize all inputs
- Use environment variables for secrets
- Implement rate limiting for external APIs

### 5. Performance

- Cache frequently accessed data
- Use pagination for large datasets
- Set reasonable timeouts
- Handle long-running operations gracefully

## Configuration

### Project-Level (.mcp.json)

```json
{
  "mcpServers": {
    "project-server": {
      "type": "stdio",
      "command": "node",
      "args": ["./mcp/server.js"]
    }
  }
}
```

### User-Level (~/.claude/mcp.json)

```json
{
  "mcpServers": {
    "global-server": {
      "type": "stdio",
      "command": "/usr/local/bin/my-mcp-server"
    }
  }
}
```

## Debugging

### Enable Debug Logging

```bash
claude --mcp-debug
```

### Common Issues

1. **Server not found**: Check command path and permissions
2. **Connection timeout**: Server may be slow to start
3. **Tool errors**: Check server logs for details
4. **Authentication**: Verify API keys in environment

## Example: Complete Server

```typescript
#!/usr/bin/env node
import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  ListToolsRequestSchema,
  CallToolRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';

const API_KEY = process.env.MY_API_KEY;

const server = new Server({
  name: 'example-server',
  version: '1.0.0',
}, {
  capabilities: { tools: {} },
});

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: [
    {
      name: 'fetch_data',
      description: 'Fetch data from external API',
      inputSchema: {
        type: 'object',
        properties: {
          endpoint: { type: 'string', description: 'API endpoint' }
        },
        required: ['endpoint']
      }
    }
  ]
}));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  if (name === 'fetch_data') {
    try {
      const response = await fetch(args.endpoint, {
        headers: { 'Authorization': `Bearer ${API_KEY}` }
      });
      const data = await response.json();
      return {
        content: [{ type: 'text', text: JSON.stringify(data, null, 2) }]
      };
    } catch (error) {
      return {
        content: [{ type: 'text', text: `Error: ${error.message}` }],
        isError: true
      };
    }
  }

  return {
    content: [{ type: 'text', text: `Unknown tool: ${name}` }],
    isError: true
  };
});

const transport = new StdioServerTransport();
server.connect(transport);
```
