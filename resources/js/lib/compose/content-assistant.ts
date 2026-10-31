import type { PlatformName } from '@/types/compose';

type SimulatedContentResponse = {
    platform: PlatformName;
    provider: 'simulated';
    text: string;
};

export type ContentAssistantResult =
    | { status: 'ready'; provider: string; text: string }
    | { status: 'empty_brief'; provider: string }
    | { status: 'unavailable'; provider: string };

export interface ContentAssistantAdapter {
    generate(
        brief: string,
        platform: PlatformName,
    ): Promise<ContentAssistantResult>;
}

const PLATFORM_PROMPTS: Record<
    PlatformName,
    { opening: string; closing: string }
> = {
    x: {
        opening: 'Quick take:',
        closing: 'What do you think?',
    },
    bluesky: {
        opening: 'A thought to share:',
        closing: 'Curious to hear your perspective.',
    },
    linkedin: {
        opening: 'A professional perspective:',
        closing: 'How has your team approached this?',
    },
    facebook: {
        opening: 'Here is an idea worth discussing:',
        closing: 'Join the conversation.',
    },
    instagram: {
        opening: 'A moment worth sharing:',
        closing: 'Save this and share your thoughts.',
    },
    threads: {
        opening: 'One thing to consider:',
        closing: 'What would you add?',
    },
    discord: {
        opening: 'Discussion starter:',
        closing: 'Share your thoughts with the community.',
    },
};

export const simulatedContentAssistant: ContentAssistantAdapter = {
    async generate(brief, platform) {
        const response = simulateContentResponse(brief, platform);

        return response === null
            ? { status: 'empty_brief', provider: 'simulated' }
            : {
                  status: 'ready',
                  provider: response.provider,
                  text: response.text,
              };
    },
};

export function simulateContentResponse(
    brief: string,
    platform: PlatformName,
): SimulatedContentResponse | null {
    const trimmedBrief = brief.trim();

    if (trimmedBrief === '') {
        return null;
    }

    const prompt = PLATFORM_PROMPTS[platform];

    return {
        platform,
        provider: 'simulated',
        text: `${prompt.opening}\n\n${trimmedBrief}\n\n${prompt.closing}`,
    };
}
