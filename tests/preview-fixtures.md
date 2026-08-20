# KaTeX preview fixtures

Use these cases in BookStack's Markdown editor to check the live preview parser. The final unmatched-delimiter case intentionally checks preview fallback behavior rather than saved-page output.

## Display math in a list

- Formula inside a list item:

  $$
  \operatorname{list}(x)
  =
  x^2 + 1
  $$

- The next list item must remain separate.

Expected: The formula renders inside the first list item. Its TeX must not contain the two-space list indentation, and the second item must remain a normal list item.

## Display math in a blockquote

> Quoted formula:
>
> $$
> \operatorname{quote}(y)
> =
> y^2 - 1
> $$
>
> Text after the formula.

Expected: The formula renders inside the blockquote. Its TeX must not contain `>` markers, and the following quoted paragraph must remain visible.

## Unclosed display delimiter

$$
x + y

## Heading after unmatched math

This paragraph must remain visible in the preview.

Expected: No display-math token is produced. The unmatched `$$` and following content are handled as ordinary Markdown; the heading and paragraph are not swallowed by the math rule.

## Four-space indented code

    $$
    x = y
    $$

Expected: The entire example remains an indented code block and no display-math token is produced.

## Closing delimiter outside a list item

- The opener is inside this list item:

  $$
  x + y

$$

This paragraph is outside the list.

Expected: The preview math rule does not use the outdented `$$` as the closing delimiter and does not consume the paragraph outside the list.
