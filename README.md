# Innertext

This is an approximate implementation of [the WHATWG innerText attribute](https://html.spec.whatwg.org/multipage/dom.html#the-innertext-idl-attribute) from a plain DOM, without any knowledge of rendering or CSS. This was drafted to be used as a replacement of the `textContent` DOM property in [microformats2](http://microformats.org/wiki/microformats2) parsers. So the outlay of a plaintext version of an element could still be easily displayed for reading without unexpected side-effects (like `<br>` elements disappearing).

For whitespace normalisation this emulates [the white space processing rules](https://drafts.csswg.org/css-text/#white-space-rules) from the CSS Text Module Level 3 specification, currently a draft from the CSS Working Group at W3C.

## Annotated Implementation

### innerText

For the 6 steps described to run for [the retrieval of innerText](https://html.spec.whatwg.org/multipage/dom.html#dom-innertext) only one change is made inside this implementation:

> 1. If this element is not being rendered, or if the user agent is a non-CSS user agent, then return the same value as the `textContent` IDL attribute on this element.

Returning just `textContent` (because we are “a non-CSS user agent”) is undesirable, so continue. The assumption is made that whatever element you are trying to read the text from is being rendered for the purpose of this first step.

This step can safely be skipped in its entirety.

### inner text collection steps

For the 10 steps described to run for [inner text collection](https://html.spec.whatwg.org/multipage/dom.html#inner-text-collection-steps) a couple of things are important:

1. It has to know whether this is the first time it is being run (i.e. directly on the element we are determining the innerText of) and whether it is currently in “pre” mode (where it knows not to touch whitespace).
2. It has to know to ignore the very first U+000A LINE FEED (LF) (`\n`) character inside `<pre>` and `<textarea>` elements, **only if** the DOM parser hasn’t already stripped it.
3. It has to be able to follow when it encounters a block it has already run whitespace processing on. For this a `BLOCK_START` and `BLOCK_END` constants are used by this implementation.

A number of changes have been made to the described steps, as follows:

> 2. If node's computed value of 'visibility' is not 'visible', then return items.

It is assumes that all nodes have `visibility` set to `visible`. There are only 6 cases of special `visibility` values in the default CSS. All to set elements that are already set to be hidden to `collapse` within a table. (See [the CSS user agent style sheet for tables](https://html.spec.whatwg.org/multipage/rendering.html#tables-2).)

This step can safely be skipped, as those edge-cases should get covered by the next step.

> 3. If node is not being rendered, then return items. For the purpose of this step, the following elements must act as described if the computed value of the 'display' property is not 'none': […]

“Being rendered” means [a very specific thing](https://html.spec.whatwg.org/multipage/rendering.html#being-rendered) here, namely that an element has a CSS layout box. The assumption of this implementation is that every DOM element has a layout box unless they are specifically set to not have one through a default styling with `display: none;`.

See [hidden elements](https://html.spec.whatwg.org/multipage/rendering.html#hidden-elements), [flow content](https://html.spec.whatwg.org/multipage/rendering.html#flow-content-3) and [tables](https://html.spec.whatwg.org/multipage/rendering.html#tables-2) for selectors that do this.

To match the assumption made in step 1 of the outer innerText, if the collection step is being run on the initial element it **must** be treated as if it is being rendered. Even if the default CSS says it isn’t.

The special rules for `select`, `optgroup`, and `option` are ignored.

> 4. If node is a Text node, […]

This step describes whitespace processing that cannot be done on a per-text-node-basis. Therefor it is only implemented as:

```
If node is a Text node, then return items as a list containing the node’s `textContent`.
```

> 5. If node is a br element, then append a string containing a single U+000A LINE FEED (LF) character to items.

Because whitespace processing does not happen in step 4 but later, the line feed introduced by `<br>` has to be guarded as a fully processed block. Therefor this step is implemented as:

```
If node is a br element, then return items as a list containing (in order) BLOCK_START, a string containing a single U+000A LINE FEED (LF) character, and BLOCK_END.
```

> 6. If node's computed value of 'display' is 'table-cell', and node's CSS box is not the last 'table-cell' box of its enclosing 'table-row' box, then append a string containing a single U+0009 CHARACTER TABULATION (tab) character to items.

> 7. If node's computed value of 'display' is 'table-row', and node's CSS box is not the last 'table-row' box of the nearest ancestor 'table' box, then append a string containing a single U+000A LINE FEED (LF) character to items.

These two steps are not currently implemented. Tables are an outstanding question.

> 8. If node is a p element, then append 2 (a required line break count) at the beginning and end of items.

Instead of `2` this implementation adds `1` as the required line break counts. This is to align with older tests and may be changed in the future.

**EXTRA STEP: Images**

After handling `<p>` elements, this implementation goes and handles `<img>` elements in accordance with older microformats parser implementations. This is implemented as:

```
If node is an img element, then:
* if node has an alt attribute, return items as a list containing only the alt attribute’s value with a single space character before and after.
* else if node has a src attribute, return items as a list containing only the src attribute’s value with a single space character before and after.
```

> 9. If node's used value of 'display' is block-level or 'table-caption', then append 1 (a required line break count) at the beginning and end of items.

This is implemented as described, with 2 assumptions:

1. There is only one element that will have a `display` of `table-caption` and that is the `caption` element (per the [default CSS for tables](https://html.spec.whatwg.org/multipage/rendering.html#tables-2)).
2. Elements are considered “block-level” if they are on [MDN’s list of block-level elements](https://developer.mozilla.org/en-US/docs/Web/HTML/Block-level_elements).

**EXTRA STEP: Whitespace handling**

1. All successive string items in the items list that are not between `BLOCK_START` and `BLOCK_END` markers are merged into one item.
2. If the current block is in “pre” mode, do not do anything with the merged string items.
3. If the current block is not in “pre” mode, apply the Whiatespace Normalisation as described below to the merged string items.
4. After merging the strings in items, prepend the list items with a BLOCK_START and append a BLOCK_END marker.

> 10. Return items.

Done! :D

### whitespace normalisation

For ease of processing, it is recommended to replace all CRLF character combinations to LFs. CRLFs and LFs are both defined as segment breaks, and by normalising to one of the two the other steps only need to work on one.

For all steps below we assume the `white-space` property of all nodes to be `normal`.

Follow [Phase One of White Space Processing: Collapsing and Transformation](https://drafts.csswg.org/css-text/#white-space-phase-1).

> 1. All spaces and tabs immediately preceding or following a segment break are removed.

The word “spaces” here is assumed to mean only U+0020 SPACE characters, and “tabs” U+0009 CHARACTER TABULATION.

> 2. Segment breaks are transformed for rendering according to the segment break transformation rules.

This is the next section of the spec being included as step 2 of the process, the steps taken are the [segment break transformation rules](https://drafts.csswg.org/css-text/#line-break-transform):

> * As with spaces, any collapsible segment break immediately following another collapsible segment break is removed.

This is implemented as collapsing all sequences of LF characters with single LF characters.

> * If the character immediately before or immediately after the segment break is the zero-width space character (U+200B), then the break is removed, leaving behind the zero-width space.

This is implemented as replacing all sequences `U+200B``U+000A` and `U+000A``U+200B` with just singular `U+200B`.

> * Otherwise, if the East Asian Width property […]

This is skipped to keep language recognition out of the implementation.

> * Otherwise, if the content language of […]

This too is skipped to keep language recognition out of the implementation.

> * Otherwise, the segment break is converted to a space (U+0020).

This is implemented as replacing any `U+000A` with `U+0020`.

> 3. Every tab is converted to a space (U+0020).

This is implemented as replacing any `U+0009` with `U+0020`.

> 4. Any space immediately following another collapsible space […] is collapsed to have zero advance width.

This is implemented as collapsing all sequences of space characters with single space characters.

**EXTRA STEP: Removing leading and trailing space**

Because of how this white space handling has been delayed in the inner text collection steps, we can be sure that any string begins and ends at a block border. This means they are either going to be surrounded by required line breaks or the start/end of the document.

As hanging white space is removed around line breaks, we can do that here. This is implemented as stripping all leading and trailing space characters.
