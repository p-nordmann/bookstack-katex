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
php artisan bookstack:install-module /absolute/path/to/bookstack-katex-0.1.0.zip
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

## Deferred edge cases

- Markdown editor live preview can show the raw delimiters until the page is saved and processed by BookStack's server-side CommonMark pipeline.
- WYSIWYG editing, server-side KaTeX rendering, and math rendering in exported documents are not included in version 0.1.0.
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

## Compatibility assumptions

Version 0.1.0 relies only on BookStack's `commonmark_environment_configure` theme event and League CommonMark 2.x extension interfaces present in BookStack 26.05.3. Future BookStack releases which change those interfaces may require a module update.
