# Contributing to MEMEX

Thank you for considering contributing to MEMEX!

## Our Philosophy

MEMEX is MIT licensed to **maximize adoption** and help as many developers as possible. We believe in **voluntary contribution** over legal obligation. Every contribution, no matter how small, helps the community.

## Found a Bug?

1. **Check existing issues**: Search [GitHub Issues](https://github.com/jacquesbh/memex-mcp/issues) first
2. **Create a detailed issue**: Include:
   - Steps to reproduce
   - Expected vs actual behavior
   - Your environment (OS, PHP version, Ollama version)
   - Error messages/logs

## Suggesting Enhancements?

1. **Open an issue** with tag `enhancement`
2. Explain:
   - What problem it solves
   - How it benefits users
   - Possible implementation approach (optional)

## Submitting Code?

### Getting Started

```bash
git clone https://github.com/jacquesbh/memex-mcp.git
cd memex-mcp
make install
make test
```

### Development Workflow

1. **Fork** the repository
2. **Create a branch**: `git checkout -b your-feature-name`
3. **Follow code style** (see [AGENTS.md](AGENTS.md)):
   - PHP 8.3+ with strict types
   - Constructor property promotion
   - Full type hints
   - No comments (self-documenting code)
4. **Write tests**: Add tests in `tests/` for new features
5. **Run tests**: `make test` must pass. `make test-mcp` for MCP integration tests (requires Node.js) must pass too.
6. **Build & test**: `make build && ./memex server`
7. **Commit**: Clear, descriptive messages
8. **Push & PR**: Open a Pull Request with description

### Code Style Checklist

- [ ] `declare(strict_types=1);` at top
- [ ] Full type hints on all parameters/returns
- [ ] Constructor DI with `readonly` for services
- [ ] Tests added for new features
- [ ] `make test` passes
- [ ] `make test-mcp` passes
- [ ] Binary builds successfully (`make build`)

## Documentation

Found a typo? Unclear instructions? PRs welcome for:
- `README.md`
- `USAGE.md`
- `AGENTS.md`
- `CONTRIBUTING.md`
- Code comments (only when absolutely necessary)

## Testing

```bash
make test          # PHPUnit tests
make test-mcp      # MCP integration tests (requires Node.js)
```

## Areas We'd Love Help With

- **Windows support** (currently unsupported)
- **Performance optimization** (vector search, caching)
- **New MCP tools** (tagging, versioning, export/import)
- **Documentation** (examples, guides, video tutorials)

## Questions?

- **GitHub Issues**: Technical questions
- **GitHub Discussions**: Ideas, show & tell

## Recognition

All contributors are credited in release notes. Significant contributions may be highlighted in README.md.

---

**By contributing, you agree that your contributions will be licensed under the MIT License.**

Thank you for helping make MEMEX better!
