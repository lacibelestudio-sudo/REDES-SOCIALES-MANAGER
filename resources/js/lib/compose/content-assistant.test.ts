import { describe, expect, it } from 'vitest';

import { simulateContentResponse } from '@/lib/compose/content-assistant';
import type { PlatformName } from '@/types/compose';

describe('simulated content assistance', () => {
    it('does not create a suggestion from an empty brief', () => {
        expect(simulateContentResponse('  \n ', 'linkedin')).toBeNull();
    });

    it.each([
        ['x', 'What do you think?'],
        ['bluesky', 'Curious to hear your perspective.'],
        ['linkedin', 'How has your team approached this?'],
        ['facebook', 'Join the conversation.'],
        ['instagram', 'Save this and share your thoughts.'],
        ['threads', 'What would you add?'],
        ['discord', 'Share your thoughts with the community.'],
    ] as const)(
        'marks the %s adaptation as simulated and leaves it for human review',
        (platform: PlatformName, closing: string) => {
            const response = simulateContentResponse(
                'A product update',
                platform,
            );

            expect(response).toEqual({
                platform,
                provider: 'simulated',
                text: expect.stringContaining(closing),
            });
            expect(response?.text).toContain('A product update');
        },
    );
});
