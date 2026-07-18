# Contributing to Bookoholik

Thank you for your interest in contributing! 🎉

## How to Contribute

### Reporting Bugs
- Open an issue with the "bug" label
- Include screenshots, browser info, and steps to reproduce

### Suggesting Features
- Open an issue with the "enhancement" label
- Describe the use case and expected behavior

### Code Contributions

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Make your changes
4. Test locally with `podman compose up -d --build`
5. Commit with clear messages: `git commit -m "feat: add book rating system"`
6. Push and open a Pull Request

### Development Setup

```bash
git clone https://github.com/YOUR_ORG/home-library.git
cd home-library
cp .env.example .env
# Edit .env with your settings
podman compose up -d --build
# App at http://localhost:3000
```

### Code Style
- PHP: PSR-12
- JavaScript/Vue: Standard + Vue recommended rules
- Commits: [Conventional Commits](https://www.conventionalcommits.org/)

### Translation Help
We welcome translations! Files are in `frontend/src/i18n/`:
- Copy `en.js` → `xx.js` (your language code)
- Translate all strings
- Register in `frontend/src/i18n/index.js`

## Code of Conduct
Be respectful, inclusive, and constructive. We're all here to build something useful together.
