# BookStack KaTeX

A minimal BookStack theme-system module which adds inline and display TeX math to the server-side CommonMark pipeline, then renders only those math placeholders with KaTeX in the browser.

## Requirements

- BookStack 26.03 or newer; implemented for and checked against BookStack 26.05.3.
- The Markdown editor. The WYSIWYG editor is outside this module's scope.
- Browser access to `cdn.jsdelivr.net` for the pinned KaTeX 0.18.4 CSS, JavaScript, and fonts.

The module uses BookStack's installed `league/commonmark` package. It has no Composer, npm, or build step and makes no BookStack core changes.

## Install

From the BookStack installation directory, run:

```sh
php artisan bookstack:install-module /absolute/path/to/bookstack-katex-0.2.0.zip
```

If the instance has no active theme, BookStack's installer offers to create one. If a module with the same name already exists, choose the installer's replace option to update it.

To remove the module, delete its installed directory from `themes/<active-theme>/modules/` (normally `bookstack-katex`). BookStack 26.05.3 does not provide a separate module-uninstall command.

## Syntax

Inline math uses one dollar sign on each side and must stay on one line:

```markdown
Einstein wrote $E = mc^2$.
```

Display math can use a single line:

```markdown
$$ E = mc^2 $$
```

Or delimiter-only lines for a multiline block:

```markdown
$$
\begin{aligned}
a^2 + b^2 &= c^2 \\
e^{i\pi} + 1 &= 0
\end{aligned}
$$
```

Display delimiters must be on their own line, with optional surrounding spaces or tabs. Inline delimiters adjacent to another `$` are ignored so they do not consume display delimiters.

## Behavior and safety

- Math is represented as opaque CommonMark AST nodes, so Markdown markers inside TeX are not interpreted.
- Code spans, fenced code blocks, and escaped dollars remain literal.
- Placeholder text is HTML-escaped server-side. The browser reads it through `textContent`.
- KaTeX runs with `trust: false`, `strict: 'warn'`, and `throwOnError: false`.
- The module does not use the KaTeX auto-render extension and does not weaken BookStack's content-security policy. If the CDN is unavailable or disallowed by the instance policy, escaped TeX remains visible instead of running another fallback renderer.
- An unmatched inline delimiter remains literal. An unmatched multiline display opener safely renders the remaining source in a `<pre>` block.
- In the live preview, an unmatched multiline display opener is left to MarkdownIt as ordinary Markdown and does not consume subsequent blocks.

## Markdown preview

Version 0.2.0 installs equivalent math rules into the Markdown editor's preview-only `markdownIt` instance through BookStack's `editor-markdown::setup` public JavaScript event. Its renderer rules call KaTeX's `renderToString()` directly, while the preview iframe setup adds the pinned KaTeX stylesheet.

The preview integration does not change the Markdown saved by BookStack. Saved pages continue to use the server-side CommonMark extension as the source of truth.

The Markdown preview supports display math nested in lists and blockquotes. Its parser reads each line from MarkdownIt's container-adjusted `bMarks + tShift` position so list indentation and blockquote markers do not become part of the TeX. Manual cases are provided in `tests/preview-fixtures.md`.

Display delimiters indented by four or more spaces relative to their current Markdown block remain indented code. A multiline display block must also close before its current list, blockquote, or other indentation container ends.

## Deferred edge cases

- WYSIWYG editing, server-side KaTeX rendering, and math rendering in exported documents are not included in version 0.2.0.
- Delimiter nesting and advanced TeX environments which need extra KaTeX extensions are intentionally outside the MVP.

## Manual regression checklist

1. `$E = mc^2$` renders inline.
2. `$$ E = mc^2 $$` renders as a display block.
3. A delimiter-only multiline block renders and preserves internal newlines.
4. `*x* + _y_` inside math is not parsed as emphasis.
5. `` `$not_math$` `` and a fenced code block containing dollars remain code.
6. `\$not_math$` leaves the escaped dollar literal.
7. `<img src=x onerror=alert(1)>` inside math is shown as an error/literal string and never becomes HTML.
8. A long display expression scrolls horizontally instead of widening the page.
9. The list and blockquote cases in `tests/preview-fixtures.md` render without including container markers in the TeX.

## Compatibility assumptions

Version 0.2.0 relies on BookStack's `commonmark_environment_configure` theme event, the `editor-markdown::setup` JavaScript event, and League CommonMark 2.x extension interfaces present in BookStack 26.05.3. Future BookStack releases which change those interfaces or the preview's `markdownIt` integration may require a module update.

## Changelog

- 0.2.0: Add direct KaTeX rendering to the Markdown editor preview, including indentation-aware list and blockquote fixtures and safe handling of unclosed display delimiters.
- 0.1.1: Correct the `InlineParserContext` namespace for League CommonMark 2.8.
