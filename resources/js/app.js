import './bootstrap';

// Load Alpine only if it's not already present (prevents duplicate instances)
if (!window.Alpine) {
	import('alpinejs').then((module) => {
		window.Alpine = module.default;
	}).catch((e) => console.error('Failed to load Alpine', e));
}

// Flowbite JS for interactive components (tooltips, dropdowns, etc.)
import 'flowbite';
