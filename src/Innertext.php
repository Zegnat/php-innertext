<?php

declare(strict_types=1);

namespace Zegnat\Innertext;

class Innertext
{
    const LF = "\n";
    const CRLF = "\r\n";
    const BLOCK_START = -1111111111;
    const BLOCK_END = -9999999999;

    const PARAGRAPH_REQ_LINE_BREAKS = 1;

    public function __construct()
    {
    }

    /**
     * @see https://html.spec.whatwg.org/multipage/dom.html#the-innertext-idl-attribute
     */
    public function innerText(\DOMNode $node): string
    {
        /**
         * Step 1:
         * If this element is not being rendered, or if the user agent is a
         * non-CSS user agent, then return the same value as the textContent
         * IDL attribute on this element.
         */
        // Skip: if we are interested in the element we assume it is rendered.

        /**
         * Step 2:
         * Let results be the list resulting in running the inner text
         * collection steps with this element. Each item in results will
         * either be a JavaScript string or a positive integer (a required
         * line break count).
         */
        $result = $this->textCollection($node, true);

        /**
         * Step 3:
         * Remove any items from results that are the empty string.
         */
        $result = \array_filter($result, function ($item) {
            return '' !== $item;
        });

        /**
         * Step 4:
         * Remove any runs of consecutive required line break count items at
         * the start or end of results.
         */
        $fromstart = true;
        $start = 0;
        $trailing = 0;
        foreach ($result as $resultitem) {
            if (\is_int($resultitem)) {
                if ($fromstart) {
                    ++$start;
                } else {
                    ++$trailing;
                }
            } else {
                $fromstart = false;
                $trailing = 0;
            }
        }
        if (0 === $trailing) {
            $trailing = null;
        } else {
            $trailing *= -1;
        }
        $result = \array_slice($result, $start, $trailing);

        /**
         * Step 5:
         * Replace each remaining run of consecutive required line break count
         * items with a string consisting of as many U+000A LINE FEED (LF)
         * characters as the maximum of the values in the required line break
         * count items.
         */
        $temp_result = [];
        $breaks = 0;
        foreach ($result as $resultitem) {
            if (\is_int($resultitem)) {
                $breaks = \max($breaks, $resultitem);
            } else {
                if (0 !== $breaks) {
                    $temp_result[] = \str_repeat("\n", $breaks);
                    $breaks = 0;
                }
                $temp_result[] = $resultitem;
            }
        }
        $result = $temp_result;

        /*
         * Step 6:
         * Return the concatenation of the string items in results.
         */
        return \implode('', $result);
    }

    /**
     * @see https://html.spec.whatwg.org/multipage/dom.html#inner-text-collection-steps
     */
    private function textCollection(\DOMNode $node, bool $outer = false, bool $pre = false): array
    {
        /*
         * Step 0:
         * Check wether current node toggle white-space pre.
         */
        if (true === \in_array(\strtolower($node->nodeName), [
            'listing',
            'plaintext',
            'pre',
            'xmp',
            'textarea',
        ])) {
            $pre = true;
        }

        /**
         * Step 1:
         * Let items be the result of running the inner text collection steps
         * with each child node of node in tree order, and then concatenating
         * the results to a single list.
         */
        $items = [];
        // WARNING: PHP DOMText returns null on childNodes, going against what
        //          is specified for DOMNote.
        if (false === $node instanceof \DOMText) {
            foreach ($node->childNodes as $childNode) {
                $items = \array_merge($items, $this->textCollection($childNode, false, $pre));
            }
        }

        /*
         * Step 2:
         * If node's computed value of 'visibility' is not 'visible', then
         * return items.
         */
        // Skip: almost no elements have a default special visibility style.

        /*
         * Step 3:
         * If node is not being rendered, then return items. For the purpose
         * of this step, the following elements must act as described if the
         * computed value of the 'display' property is not 'none':
         * * select elements have an associated non-replaced inline CSS box
         *   whose child boxes include only those of optgroup and option
         *   element child nodes;
         * * optgroup elements have an associated non-replaced block-level CSS
         *   box whose child boxes include only those of option element child
         *   nodes; and
         * * option element have an associated non-replaced block-level CSS
         *   box whose child boxes are as normal for non-replaced block-level
         *   CSS boxes.
         */
        if (false === $outer && false === $node instanceof \DOMText && false === $this->isBeingRendered($node)) {
            // If a node is not being rendered, by definition its child nodes
            // are not being rendered. Should be safe to return empty $items.
            return [];
        }

        /*
         * Step 4:
         * If node is a Text node, then for each CSS text box produced by
         * node, in content order, compute the text of the box after
         * application of the CSS 'white-space' processing rules and
         * 'text-transform' rules, set items to the list of the resulting
         * strings, and return items. The CSS 'white-space' processing rules
         * are slightly modified: collapsible spaces at the end of lines are
         * always collapsed, but they are only removed if the line is the last
         * line of the block, or it ends with a br element. Soft hyphens
         * should be preserved.
         */
        if (XML_TEXT_NODE === $node->nodeType) {
            // Add the text as a single item to its container element’s list.
            return [$node->textContent];
        }

        /*
         * Step 5:
         * If node is a br element, then append a string containing a single
         * U+000A LINE FEED (LF) character to items.
         */
        // NOTE: We mark this as a separate block context as it should not
        // have further whitespace processing applied to it!
        if ('br' === \strtolower($node->nodeName)) {
            return [
                self::BLOCK_START,
                "\n",
                self::BLOCK_END,
            ];
        }

        /*
         * Step 6:
         *
         */
        // @TODO

        /*
         * Step 7:
         *
         */
        // @TODO

        /*
         * Step 8:
         * If node is a p element, then append 2 (a required line break count)
         * at the beginning and end of items.
         */
        if ('p' === \strtolower($node->nodeName)) {
            \array_unshift($items, self::PARAGRAPH_REQ_LINE_BREAKS);
            $items[] = self::PARAGRAPH_REQ_LINE_BREAKS;
        }

        /*
         * NEW Step 8.5:
         * Handling images by replacing them with a string per mf2 parsing:
         * If the image has an alt attribute, use it with whitespace trimmed.
         * Else use the absolute URL gained from resolving the src attribute.
         * Append and prepend a space to the used value.
         */
        if ('img' === \strtolower($node->nodeName)) {
            if ($node->hasAttribute('alt')) {
                $value = \trim($node->getAttribute('alt'));
            } elseif ($node->hasAttribute('src')) {
                $value = \trim($node->getAttribute('src'));
            }
            if (true === $outer) {
                return [$value];
            } else {
                return [' '.$value.' '];
            }
        }

        /*
         * Step 9:
         * If node's used value of 'display' is block-level or
         * 'table-caption', then append 1 (a required line break count) at the
         * beginning and end of items.
         */
        if ($this->isBlockLevel($node) || 'caption' === \strtolower($node->nodeName)) {
            \array_unshift($items, 1);
            $items[] = 1;
        }

        /*
         * Step 9.5:
         * 1. Merge all consecutive string values,
         * 2. normalise whitespace within the resulting strings,
         * 3. add block markers around the list.
         */
        if (true === $outer || $this->isBlockLevel($node)) {
            $tmp_items = [];
            $tmp_string = null;
            $innerblock = 0;
            foreach ($items as $item) {
                // If there is a string in memory, and a non string item is
                // found, append the string to our new items and clear it.
                if (null !== $tmp_string && false === \is_string($item)) {
                    if (true === $pre) {
                        // If we are in a element that has white-space pre,
                        // skip all whitespace processing.
                        $new_items[] = $tmp_string;
                    } else {
                        $new_items[] = $this->normaliseWhitespace($tmp_string);
                    }
                    $tmp_string = null;
                }
                // Nested block starts, up our counter.
                if (self::BLOCK_START === $item) {
                    ++$innerblock;
                }
                // If we are in a nested block, just put everything through.
                if (0 < $innerblock) {
                    // Nested block ends here, decrease our counter.
                    if (self::BLOCK_END === $item) {
                        --$innerblock;
                    }
                    $new_items[] = $item;
                    // Next item.
                    continue;
                }
                // We are looking at an item inside the current block.
                if (\is_string($item)) {
                    // Append any consecutive strings.
                    $tmp_string .= $item;
                } else {
                    // Not a string. Probably a required line break, pass through.
                    $new_items[] = $item;
                }
            }
            $items = $new_items;
            \array_unshift($items, self::BLOCK_START);
            $items[] = self::BLOCK_END;
        }

        /*
         * Step 10:
         * Return items.
         */
        return $items;
    }

    /**
     * @see https://drafts.csswg.org/css-text/#white-space-rules
     */
    private function normaliseWhitespace(string $string): string
    {
        // Normalise CRLF to LF first. We treat CRLF as a segment break,
        // meaning they will be transformed anyway. Doing it first saves on
        // some edge-case matching.
        $string = \str_replace(self::CRLF, self::LF, $string);

        /**
         * Step 1:
         * All spaces and tabs immediately preceding or following a segment
         * break are removed.
         */
        $string = \preg_replace('@[ \t]*\n[ \t]*@', self::LF, $string);

        /**
         * Step 2:
         * Segment breaks are transformed for rendering according to the
         * segment break transformation rules.
         * (See 4.1.2).
         */
        /**
         * As with spaces, any collapsible segment break immediately following
         * another collapsible segment break is removed.
         */
        $string = \preg_replace('@\n\n*@', self::LF, $string);
        /**
         * If the character immediately before or immediately after the
         * segment break is the zero-width space character (U+200B), then the
         * break is removed, leaving behind the zero-width space.
         */
        // PHP 7 has unicode escapes, but older versions do not...
        $string = \str_replace(["\xE2\x80\x8B\n", "\n\xE2\x80\x8B"], "\xE2\x80\x8B", $string);
        /**
         * Otherwise, if the East Asian Width property... NOPE.
         */
        /**
         * Otherwise, if the content language of... NOPE.
         */
        /**
         * Otherwise, the segment break is converted to a space (U+0020).
         */
        $string = \str_replace("\n", ' ', $string);

        /**
         * Step 3:
         * Every tab is converted to a space (U+0020).
         */
        $string = \str_replace("\t", ' ', $string);

        /**
         * Step 4:
         * Any space immediately following another collapsible space—even one
         * outside the boundary of the inline containing that space, provided
         * both spaces are within the same inline formatting context—is
         * collapsed to have zero advance width. (It is invisible, but retains
         * its soft wrap opportunity, if any.).
         */
        $string = \preg_replace('@ +@', ' ', $string);

        /**
         * NEW Step 5:
         * Remove any leading and trailing spaces (U+0020). Because strings
         * have been concatenated within their inline context already they are
         * guaranteed to be at the start of a block and imidiately followed by
         * a new block (or the end of the document).
         */
        $string = \trim($string, ' ');

        return $string;
    }

    /**
     * @see https://html.spec.whatwg.org/multipage/rendering.html#hidden-elements
     * @see https://html.spec.whatwg.org/multipage/rendering.html#flow-content-3
     * @see https://html.spec.whatwg.org/multipage/rendering.html#tables-2
     */
    private function isBeingRendered(\DOMNode $node): bool
    {
        if (true === \in_array(\strtolower($node->nodeName), [
            'area',
            'base',
            'basefont',
            'datalist',
            'head',
            'link',
            'meta',
            'noembed',
            'noframes',
            'param',
            'rp',
            'script',
            'source',
            'style',
            'template',
            'track',
            'title',
        ])) {
            return false;
        }
        if ($node->hasAttribute('hidden') && 'embed' !== \strtolower($node->nodeName)) {
            return false;
        }
        if ('input' === \strtolower($node->nodeName) && 'hidden' === \strtolower($node->getAttribute('type'))) {
            return false;
        }
        if ('dialog' === \strtolower($node->nodeName) && false === $node->hasAttribute('open')) {
            return false;
        }
        if ('form' === \strtolower($node->nodeName) && true === \in_array(\strtolower($node->parentNode->nodeName), [
            'table',
            'thead',
            'tbody',
            'tfoot',
            'tr',
        ])) {
            return false;
        }

        return true;
    }

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Block-level_elements
     */
    private function isBlockLevel(\DOMNode $node): bool
    {
        return \in_array(\strtolower($node->nodeName), [
            'address',
            'article',
            'aside',
            'blockquote',
            'details',
            'dialog',
            'dd',
            'div',
            'dl',
            'dt',
            'fieldset',
            'figcaption',
            'figure',
            'footer',
            'form',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'header',
            'hgroup',
            'hr',
            'li',
            'main',
            'nav',
            'ol',
            'p',
            'pre',
            'section',
            'table',
            'ul',
        ]);
    }
}
