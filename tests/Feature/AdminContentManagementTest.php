<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContentManagementTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $this->staff = User::factory()->create();
        $this->venue->users()->attach($this->staff->id, ['role' => 'staff', 'status' => 'active']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function hallForm(array $overrides = []): array
    {
        return [
            'name' => 'Garden Hall', 'setting' => 'outdoor', 'min_guests' => 50, 'max_guests' => 300, 'base_price' => 25000000,
            'size_sqm' => 400, 'view_type' => 'garden', 'seating_styles' => 'Banquet, Long table', 'amenities' => 'Sound system, Rain cover',
            'extra_hour_price' => 2000000, 'extra_hour_available' => 1, 'catering_included' => 1, 'is_active' => 1,
            'translations' => ['en' => ['name' => 'Garden Hall', 'description' => 'Open air'], 'ja' => ['name' => 'ガーデンホール', 'description' => null]],
            ...$overrides,
        ];
    }

    public function test_staff_create_a_hall_and_its_codes_slug_and_translations_are_stored(): void
    {
        $this->actingAs($this->staff)->post(route('admin.halls.store'), $this->hallForm())->assertRedirect(route('admin.halls.index'));

        $hall = $this->venue->halls()->firstOrFail();
        $this->assertSame('garden-hall', $hall->slug);
        $this->assertSame(['banquet', 'long_table'], $hall->seating_styles);
        $this->assertSame(['sound_system', 'rain_cover'], $hall->amenities);
        $this->assertSame('ガーデンホール', $hall->translations['ja']['name']);
        $this->assertTrue($hall->is_active);
        $this->assertTrue($hall->extra_hour_available);
    }

    public function test_two_halls_with_the_same_name_get_different_slugs(): void
    {
        $this->actingAs($this->staff)->post(route('admin.halls.store'), $this->hallForm());
        $this->actingAs($this->staff)->post(route('admin.halls.store'), $this->hallForm());

        $this->assertSame(['garden-hall', 'garden-hall-1'], $this->venue->halls()->orderBy('id')->pluck('slug')->all());
    }

    public function test_editing_a_hall_keeps_its_slug_and_unticked_boxes_turn_the_flags_off(): void
    {
        $this->actingAs($this->staff)->post(route('admin.halls.store'), $this->hallForm());
        $hall = $this->venue->halls()->firstOrFail();

        $this->actingAs($this->staff)
            ->put(route('admin.halls.update', $hall), $this->hallForm(['name' => 'Rose Hall', 'is_active' => 0, 'catering_included' => 0, 'extra_hour_available' => 0]))
            ->assertRedirect(route('admin.halls.index'));

        $hall->refresh();
        $this->assertSame('Rose Hall', $hall->name);
        $this->assertSame('garden-hall', $hall->slug);
        $this->assertFalse($hall->is_active);
        $this->assertFalse($hall->catering_included);
        $this->assertFalse($hall->extra_hour_available);
    }

    public function test_hall_form_rejects_bad_input_and_stores_nothing(): void
    {
        $bad = [
            ['name' => ''],
            ['setting' => 'underwater'],
            ['min_guests' => 0],
            ['min_guests' => 200, 'max_guests' => 100],
            ['max_guests' => 99999],
            ['base_price' => -1],
            ['base_price' => 'free'],
            ['size_sqm' => 0],
        ];

        foreach ($bad as $overrides) {
            $this->actingAs($this->staff)->post(route('admin.halls.store'), $this->hallForm($overrides))->assertSessionHasErrors();
        }

        $this->assertSame(0, $this->venue->halls()->count());
    }

    public function test_the_hall_form_pages_render_and_the_hall_list_shows_the_venues_halls(): void
    {
        $hall = $this->venue->halls()->create(['name' => 'Garden Hall', 'slug' => 'garden-hall', 'base_price' => 1, 'is_active' => true]);

        $this->actingAs($this->staff)->get(route('admin.halls.index'))->assertOk()->assertSee('Garden Hall');
        $this->actingAs($this->staff)->get(route('admin.halls.create'))->assertOk();
        $this->actingAs($this->staff)->get(route('admin.halls.edit', $hall))->assertOk()->assertSee('Garden Hall');
    }

    public function test_staff_cannot_open_edit_or_delete_a_hall_of_another_venue(): void
    {
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $foreign = $other->halls()->create(['name' => 'Foreign', 'slug' => 'foreign', 'base_price' => 1, 'is_active' => true]);

        $this->actingAs($this->staff)->get(route('admin.halls.edit', $foreign))->assertNotFound();
        $this->actingAs($this->staff)->put(route('admin.halls.update', $foreign), $this->hallForm())->assertNotFound();
        $this->actingAs($this->staff)->delete(route('admin.halls.destroy', $foreign))->assertNotFound();
        $this->assertSame('Foreign', $foreign->fresh()->name);
    }

    public function test_an_unused_hall_can_be_deleted(): void
    {
        $hall = $this->venue->halls()->create(['name' => 'Garden Hall', 'slug' => 'garden-hall', 'base_price' => 1, 'is_active' => true]);

        $this->actingAs($this->staff)->delete(route('admin.halls.destroy', $hall))->assertRedirect(route('admin.halls.index'));

        $this->assertSoftDeleted($hall);
    }

    public function test_staff_create_update_and_delete_a_knowledge_entry(): void
    {
        $form = [
            'category' => 'policies', 'title' => 'Cancellation', 'body' => 'Refund within 30 days.', 'tags' => 'refund, cancel',
            'translations' => ['en' => ['title' => 'Cancellation', 'body' => 'Refund in 30 days.'], 'ja' => ['title' => null, 'body' => null]],
            'is_active' => 1,
        ];

        $this->actingAs($this->staff)->post(route('admin.knowledge-items.store'), $form)->assertRedirect(route('admin.knowledge-items.index'));

        $item = $this->venue->knowledgeItems()->firstOrFail();
        $this->assertSame(['refund', 'cancel'], $item->tags);
        $this->assertSame('Refund in 30 days.', $item->translatedBody('en'));
        $this->assertSame('Refund within 30 days.', $item->translatedBody('ja'));

        $this->actingAs($this->staff)->put(route('admin.knowledge-items.update', $item), [...$form, 'title' => 'Refunds', 'is_active' => 0])->assertRedirect();
        $this->assertSame('Refunds', $item->fresh()->title);
        $this->assertFalse($item->fresh()->is_active);

        $this->actingAs($this->staff)->delete(route('admin.knowledge-items.destroy', $item))->assertRedirect();
        $this->assertModelMissing($item);
    }

    public function test_knowledge_form_rejects_bad_input_and_the_list_and_form_pages_render(): void
    {
        $this->actingAs($this->staff)->post(route('admin.knowledge-items.store'), ['category' => 'secrets', 'title' => '', 'body' => ''])
            ->assertSessionHasErrors(['category', 'title', 'body']);
        $this->assertSame(0, $this->venue->knowledgeItems()->count());

        $item = $this->venue->knowledgeItems()->create(['category' => 'faq', 'title' => 'Parking?', 'body' => 'Yes.', 'is_active' => true]);
        $this->actingAs($this->staff)->get(route('admin.knowledge-items.index'))->assertOk()->assertSee('Parking?');
        $this->actingAs($this->staff)->get(route('admin.knowledge-items.create'))->assertOk();
        $this->actingAs($this->staff)->get(route('admin.knowledge-items.edit', $item))->assertOk();
    }

    public function test_staff_cannot_open_edit_or_delete_a_knowledge_entry_of_another_venue(): void
    {
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $foreign = $other->knowledgeItems()->create(['category' => 'faq', 'title' => 'Foreign', 'body' => 'x', 'is_active' => true]);

        $this->actingAs($this->staff)->get(route('admin.knowledge-items.edit', $foreign))->assertNotFound();
        $this->actingAs($this->staff)->delete(route('admin.knowledge-items.destroy', $foreign))->assertNotFound();
        $this->assertModelExists($foreign);
    }
}
