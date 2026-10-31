import { readdirSync } from 'node:fs';
import { readFile, readdir } from 'node:fs/promises';
import { join, relative } from 'node:path';

import { describe, expect, it } from 'vitest';

const FORBIDDEN_CACHE_APIS = ['prefetch', 'cacheFor', 'cacheTags', 'flushAll'];
const SOURCE_ROOT = join(process.cwd(), 'resources/js');
const isSourceFile = (name: string) =>
    /\.(ts|tsx)$/.test(name) && !/\.(test|spec)\.(ts|tsx)$/.test(name);
const SOURCE_DIRECTORIES = readdirSync(SOURCE_ROOT, { withFileTypes: true })
    .filter(
        (entry) =>
            entry.isDirectory() &&
            entry.name !== '__tests__' &&
            entry.name !== 'test-utils',
    )
    .map((entry) => entry.name);
const ROOT_SOURCE_FILES = readdirSync(SOURCE_ROOT, { withFileTypes: true })
    .filter((entry) => entry.isFile() && isSourceFile(entry.name))
    .map((entry) => join(SOURCE_ROOT, entry.name));

async function sourceFiles(dir: string): Promise<string[]> {
    const entries = await readdir(dir, { withFileTypes: true });

    return (
        await Promise.all(
            entries.map(async (entry) => {
                const path = join(dir, entry.name);

                if (entry.isDirectory()) {
                    if (
                        entry.name === '__tests__' ||
                        entry.name === 'test-utils'
                    ) {
                        return [];
                    }

                    return sourceFiles(path);
                }

                return entry.isFile() && isSourceFile(entry.name) ? [path] : [];
            }),
        )
    ).flat();
}

async function findCacheApiUses(files: string[]): Promise<string[]> {
    const offenders: string[] = [];

    for (let offset = 0; offset < files.length; offset += 8) {
        const batch = await Promise.all(
            files.slice(offset, offset + 8).map(async (path) => ({
                path: relative(process.cwd(), path),
                source: await readFile(path, 'utf8'),
            })),
        );

        offenders.push(
            ...batch
                .filter(({ source }) =>
                    FORBIDDEN_CACHE_APIS.some((term) => source.includes(term)),
                )
                .map(({ path }) => path),
        );
    }

    return offenders;
}

describe('Inertia cache policy', () => {
    for (const directory of SOURCE_DIRECTORIES) {
        it(`does not use client-side page prefetch caching in ${directory}`, async () => {
            const files = await sourceFiles(join(SOURCE_ROOT, directory));
            const offenders = await findCacheApiUses(files);

            expect(offenders).toEqual([]);
        });
    }

    it('does not use client-side page prefetch caching in root source files', async () => {
        const offenders = await findCacheApiUses(ROOT_SOURCE_FILES);

        expect(offenders).toEqual([]);
    });
});
