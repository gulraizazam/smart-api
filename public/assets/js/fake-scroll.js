function DoubleScroll(element) {
    var scrollbar = document.createElement('div');
    scrollbar.className='fake-scroll';
    scrollbar.appendChild(document.createElement('div'));
    scrollbar.style.overflow = 'auto';
    scrollbar.style.overflowY = 'hidden';
    scrollbar.firstChild.style.width = element.scrollWidth+'px';
    scrollbar.firstChild.style.paddingTop = '1px';
    scrollbar.firstChild.style.minWidth = '100%';
    scrollbar.firstChild.appendChild(document.createTextNode('\xA0'));
    scrollbar.onscroll = function() {
        element.scrollLeft = scrollbar.scrollLeft;
    };
    element.onscroll = function() {
        scrollbar.scrollLeft = element.scrollLeft;
    };
    element.parentNode.insertBefore(scrollbar, element);
}

DoubleScroll(document.getElementById('topscroll'));
