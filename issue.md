It is still wrong.

The arrow is still inside a separate square button, and the blue focus border makes it look broken. The arrow is also overlapping the “Compare period” heading instead of sitting neatly on the right side.

Send this to your developer:

Please fix the Compare period accordion header properly.

The current implementation is still incorrect:

The chevron is inside a separate square button.
The square has a visible blue focus outline.
The arrow overlaps the “Compare period” text.
The heading is too large.
The arrow and heading are not aligned as one component.

The entire accordion header must be one full-width clickable button.

It should look like this:

Compare period ▼

Not like this:

[▼]Compare period

Requirements:

Remove the separate square button around the arrow.
Remove the separate border, background and blue focus outline from the arrow.
Make the complete heading row clickable.
Align “Compare period” on the left.
Align the chevron on the far right.
Vertically centre the text and chevron.
Reduce the heading font size to approximately 18px.
Use a downward chevron when expanded.
Use a right-facing chevron when collapsed.
Apply hover and focus styling to the entire header row, not the chevron.
Ensure the heading does not overlap the drawer edge or any other element.

Use markup similar to:

<button
type="button"
class="filter-accordion-header"
aria-expanded="true"

>

    <span class="filter-accordion-title">Compare period</span>
    <span class="filter-accordion-chevron" aria-hidden="true">⌄</span>

</button>

Use styling similar to:

.filter-accordion-header {
display: flex;
align-items: center;
justify-content: space-between;
width: 100%;
padding: 12px 0;
border: 0;
background: transparent;
text-align: left;
cursor: pointer;
}

.filter-accordion-title {
font-size: 18px;
font-weight: 600;
line-height: 1.3;
}

.filter-accordion-chevron {
display: inline-flex;
align-items: center;
justify-content: center;
flex-shrink: 0;
width: auto;
height: auto;
margin-left: 12px;
border: 0;
background: transparent;
}

.filter-accordion-header:focus-visible {
outline: 2px solid var(--bs-primary);
outline-offset: 2px;
}
