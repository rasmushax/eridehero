/**
 * Super Accordion Block JS
 *
 * Handles toggling the accordion panel visibility.
 */
document.addEventListener('DOMContentLoaded', function () {
  // Select all accordion headers within super-accordion blocks
  const accordionHeaders = document.querySelectorAll('.super-accordion-block .accordion-header');

  accordionHeaders.forEach(header => {
    header.addEventListener('click', function () {
      const accordionItem = this.closest('.accordion-item');
      const panel = this.nextElementSibling; // Assumes panel is the immediate next sibling
      const isExpanded = this.getAttribute('aria-expanded') === 'true';

      // Toggle ARIA attribute
      this.setAttribute('aria-expanded', !isExpanded);

      // Toggle classes for styling and transition
      accordionItem.classList.toggle('accordion-active');
      panel.classList.toggle('open');

      // Optional: Set focus to the panel when opening for accessibility
      // if (!isExpanded) {
      //   panel.focus(); // May need tabindex="-1" on the panel div
      // }
    });
  });
});