document.addEventListener('DOMContentLoaded', () => {
	const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';
	const navLinks = document.querySelectorAll('[data-dashboard-nav]');
	const sidebarMenu = document.getElementById('sidebar-menu');

	navLinks.forEach((link) => {
		const href = link.getAttribute('href');
		if (!href) {
			return;
		}

		const normalizedHref = href.replace(/\/+$/, '') || '/';
		const isActive =
			(currentPath === '/' && normalizedHref === '/dashboard') ||
			currentPath === normalizedHref;

		link.classList.toggle('active', isActive);

		if (isActive) {
			const dropdownMenu = link.closest('.dropdown-menu');
			const dropdownItem = dropdownMenu?.previousElementSibling;
			const dropdownParent = link.closest('.dropdown');

			dropdownMenu?.classList.add('show');
			dropdownItem?.classList.add('active');
			dropdownItem?.setAttribute('aria-expanded', 'true');
			dropdownParent?.classList.add('show');
		}

		link.addEventListener('click', () => {
			if (window.innerWidth >= 992 || !sidebarMenu || !window.bootstrap?.Collapse) {
				return;
			}

			const collapse = window.bootstrap.Collapse.getOrCreateInstance(sidebarMenu, {
				toggle: false,
			});
			collapse.hide();
		});
	});
});
