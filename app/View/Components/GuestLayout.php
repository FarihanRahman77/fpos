<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    /**
     * @param string      $variant         'login' renders the split login layout
     * @param string|null $brandLogo       Settings logo path (public/)
     * @param string|null $brandBackground Settings › Login Page Background path (public/)
     * @param int         $brandOverlay    % of brand color laid over the background image
     */
    public function __construct(
        public string $variant = 'default',
        public ?string $brandLogo = null,
        public ?string $brandBackground = null,
        public int $brandOverlay = 70,
    ) {
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.guest');
    }
}
