<?php

namespace BookStackKatex;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Inline\AbstractInline;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use League\CommonMark\Parser\MarkdownParserStateInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class MathExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addBlockStartParser(new DisplayMathStartParser(), 100);
        $environment->addInlineParser(new InlineMathParser(), 100);
        $environment->addRenderer(InlineMath::class, new InlineMathRenderer());
        $environment->addRenderer(DisplayMath::class, new DisplayMathRenderer());
    }
}

final class InlineMath extends AbstractInline
{
    public function __construct(private readonly string $tex)
    {
        parent::__construct();
    }

    public function getTex(): string
    {
        return $this->tex;
    }
}

final class DisplayMath extends AbstractBlock
{
    public function __construct(
        private string $tex = '',
        private bool $closed = false,
    ) {
        parent::__construct();
    }

    public function getTex(): string
    {
        return $this->tex;
    }

    public function setTex(string $tex): void
    {
        $this->tex = $tex;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function markClosed(): void
    {
        $this->closed = true;
    }
}

final class InlineMathParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::string('$');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        $openingPosition = $cursor->getPosition();
        $previousCharacter = $openingPosition > 0
            ? $cursor->getSubstring($openingPosition - 1, 1)
            : null;

        if ($this->isEscapedAt($cursor, $openingPosition)
            || $previousCharacter === '$'
            || $cursor->getSubstring($openingPosition + 1, 1) === '$') {
            return false;
        }

        $remainder = $cursor->getRemainder();
        $length = mb_strlen($remainder, 'UTF-8');

        for ($offset = 1; $offset < $length; $offset++) {
            $character = mb_substr($remainder, $offset, 1, 'UTF-8');
            if ($character === "\n" || $character === "\r") {
                break;
            }

            if ($character !== '$') {
                continue;
            }

            $closingPosition = $openingPosition + $offset;
            $previous = mb_substr($remainder, $offset - 1, 1, 'UTF-8');
            $next = mb_substr($remainder, $offset + 1, 1, 'UTF-8');
            if ($this->isEscapedAt($cursor, $closingPosition) || $previous === '$' || $next === '$') {
                continue;
            }

            $tex = mb_substr($remainder, 1, $offset - 1, 'UTF-8');
            $cursor->advanceBy($offset + 1);
            $inlineContext->getContainer()->appendChild(new InlineMath($tex));

            return true;
        }

        return false;
    }

    private function isEscapedAt(Cursor $cursor, int $position): bool
    {
        $backslashes = 0;
        for ($offset = $position - 1; $offset >= 0; $offset--) {
            if ($cursor->getSubstring($offset, 1) !== '\\') {
                break;
            }

            $backslashes++;
        }

        return ($backslashes % 2) === 1;
    }
}

final class DisplayMathStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented()) {
            return BlockStart::none();
        }

        $line = trim($cursor->getRemainder(), " \t");
        if (!str_starts_with($line, '$$') || str_starts_with($line, '$$$')) {
            return BlockStart::none();
        }

        if ($line === '$$') {
            $cursor->advanceToEnd();

            return BlockStart::of(new DisplayMathParser())->at($cursor);
        }

        if (strlen($line) >= 4 && str_ends_with($line, '$$')) {
            $tex = substr($line, 2, -2);
            $cursor->advanceToEnd();

            return BlockStart::of(new DisplayMathParser($tex))->at($cursor);
        }

        return BlockStart::none();
    }
}

final class DisplayMathParser extends AbstractBlockContinueParser
{
    private DisplayMath $block;
    private bool $singleLine;
    private bool $skipOpeningLine;

    /** @var list<string> */
    private array $lines = [];

    public function __construct(?string $singleLineTex = null)
    {
        $this->singleLine = $singleLineTex !== null;
        $this->block = new DisplayMath($singleLineTex ?? '', $singleLineTex !== null);
        $this->skipOpeningLine = $singleLineTex === null;
    }

    public function getBlock(): DisplayMath
    {
        return $this->block;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        if ($this->block->isClosed()) {
            return BlockContinue::none();
        }

        if (trim($cursor->getRemainder(), " \t") === '$$') {
            $this->block->markClosed();

            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }

    public function addLine(string $line): void
    {
        if ($this->singleLine) {
            return;
        }

        if ($this->skipOpeningLine) {
            $this->skipOpeningLine = false;

            return;
        }

        $this->lines[] = $line;
    }

    public function closeBlock(): void
    {
        if (!$this->singleLine && $this->lines !== []) {
            $this->block->setTex(implode("\n", $this->lines));
        }
    }
}

final class InlineMathRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (!$node instanceof InlineMath) {
            throw new \InvalidArgumentException('InlineMathRenderer requires an InlineMath node.');
        }

        return '<span class="bookstack-katex-inline">'
            . htmlspecialchars($node->getTex(), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8')
            . '</span>';
    }
}

final class DisplayMathRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (!$node instanceof DisplayMath) {
            throw new \InvalidArgumentException('DisplayMathRenderer requires a DisplayMath node.');
        }

        $tex = htmlspecialchars($node->getTex(), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        if (!$node->isClosed()) {
            return '<pre>$$' . ($tex === '' ? '' : "\n" . $tex) . '</pre>';
        }

        return '<div class="bookstack-katex-display">' . $tex . '</div>';
    }
}
