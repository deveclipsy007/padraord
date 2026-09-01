<?php

namespace Tests\Feature;

use Tests\TestCase;

class VisualSystemContractTest extends TestCase
{
    public function test_the_visual_system_exposes_the_rd_os_shell_contract(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('.rd-os-shell', $css);
        $this->assertStringContainsString('.mobile-nav', $css);
        $this->assertStringContainsString('backdrop-filter', $css);
        $this->assertStringContainsString('--rd-violet', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
    }

    public function test_sidebar_has_a_subtle_aurora_animation_with_a_reduced_motion_fallback(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('rd-aurora-drift', $css);
        $this->assertStringContainsString('rd-aurora-shimmer', $css);
        $this->assertStringContainsString('.sidebar::before', $css);
        $this->assertStringContainsString('.sidebar::after', $css);
        $this->assertStringContainsString('animation: none !important', $css);
    }

    public function test_sidebar_aurora_uses_only_purple_layers_with_depth_motion(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('rd-aurora-pulse', $css);
        $this->assertStringContainsString('rgba(138, 91, 235, .31)', $css);
        $this->assertStringContainsString('rgba(178, 128, 255, .10)', $css);
        $this->assertStringNotContainsString('rgba(68,135,226', $css);
        $this->assertStringNotContainsString('rgba(73,201,169', $css);
        $this->assertStringNotContainsString('rgba(92,229,186', $css);
    }

    public function test_sidebar_keeps_the_dark_shell_with_a_compact_purple_accent(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('background: linear-gradient(150deg, rgba(20, 19, 29, .97), rgba(12, 12, 17, .92));', $css);
        $this->assertStringContainsString('width: 320px; height: 320px; top: -160px; right: -165px;', $css);
        $this->assertStringContainsString('rgba(138, 91, 235, .31)', $css);
        $this->assertStringNotContainsString('inset: -28% -64% -20% -45%', $css);
    }

    public function test_live_pipeline_is_a_single_drag_scrollable_horizontal_lane(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $page = file_get_contents(resource_path('js/pages/Pipeline.tsx'));

        $this->assertStringContainsString('.pipeline-full { position: relative; display: flex; flex-wrap: nowrap;', $css);
        $this->assertStringContainsString('scroll-snap-type: x proximity;', $css);
        $this->assertStringContainsString('.pipeline-full.is-dragging', $css);
        $this->assertStringContainsString('flex: 0 0 190px;', $css);
        $this->assertStringContainsString('onPointerDown', $page);
        $this->assertStringContainsString('onPointerMove', $page);
        $this->assertStringContainsString('onPointerUp', $page);
    }

    public function test_pipeline_cards_can_be_dragged_between_stage_lanes(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $page = file_get_contents(resource_path('js/pages/Pipeline.tsx'));

        $this->assertStringContainsString('draggable', $page);
        $this->assertStringContainsString('onDragStart', $page);
        $this->assertStringContainsString('onDragOver', $page);
        $this->assertStringContainsString('onDrop', $page);
        $this->assertStringContainsString('router.post(`/opportunities/', $page);
        $this->assertStringContainsString('/stage', $page);
        $this->assertStringContainsString('.pipeline-lane.is-drop-target', $css);
        $this->assertStringContainsString('.pipeline-card.is-card-dragging', $css);
    }
}
