window.addEventListener('DOMContentLoaded', () => {

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      const id = entry.target.getAttribute('id');
      if (entry.intersectionRatio > 0) {
		
		  let allElements = Array.from(document.querySelectorAll('#toc li'));
			for (let lmnt of allElements) {
			  lmnt.classList.remove('visible');
			}
		  
		  var thislmnt = document.querySelector(`#toc a[href="#${id}"]`);
        thislmnt.parentElement.classList.add('visible');
		thislmnt.closest(".h2").classList.add('visible');
      } 
    });
  });

  // Track all sections that have an `id` applied
  document.querySelectorAll('.articlecontent h2, .articlecontent h3').forEach((section) => {
    observer.observe(section);
  });
  
})