export const MAX_FORM_FIELD_OPTIONS = 100;

export interface ChoicePasteParseResult {
  labels: string[];
  blankCount: number;
  delimiter: "line" | "tab" | "comma";
}

export interface ChoiceImportPlan {
  labels: string[];
  duplicates: number;
  limitSkipped: number;
  blankCount: number;
  parsed: number;
}

function normalizedOption(value: string): string {
  return value.trim().toLowerCase();
}

export function parseChoicePaste(input: string): ChoicePasteParseResult {
  const normalizedInput = input.replace(/\r\n?/g, "\n");
  const trimmedInput = normalizedInput.trim();
  const lines = normalizedInput.split("\n");
  const isOneLinePaste = !trimmedInput.includes("\n");
  const delimiter =
    isOneLinePaste && trimmedInput.includes("\t")
      ? "tab"
      : isOneLinePaste && trimmedInput.includes(",")
        ? "comma"
        : normalizedInput.includes("\t")
          ? "tab"
          : "line";

  const rawEntries =
    delimiter === "comma"
      ? trimmedInput.split(",")
      : lines.flatMap((line) =>
          line.includes("\t") ? line.split("\t") : [line],
        );
  const labels = rawEntries.map((entry) => entry.trim()).filter(Boolean);

  return {
    labels,
    blankCount: rawEntries.length - labels.length,
    delimiter,
  };
}

export function prepareChoiceImport(
  input: string,
  existingOptions: Record<string, string>,
  maxOptions = MAX_FORM_FIELD_OPTIONS,
): ChoiceImportPlan {
  const parsed = parseChoicePaste(input);
  const existing = new Set(
    Object.entries(existingOptions).flatMap(([key, label]) => [
      normalizedOption(key),
      normalizedOption(label),
    ]),
  );
  const pasted = new Set<string>();
  const labels: string[] = [];
  let duplicates = 0;

  parsed.labels.forEach((label) => {
    const normalized = normalizedOption(label);

    if (existing.has(normalized) || pasted.has(normalized)) {
      duplicates += 1;
      return;
    }

    pasted.add(normalized);
    labels.push(label);
  });

  const capacity = Math.max(
    0,
    maxOptions - Object.keys(existingOptions).length,
  );
  const acceptedLabels = labels.slice(0, capacity);

  return {
    labels: acceptedLabels,
    duplicates,
    limitSkipped: labels.length - acceptedLabels.length,
    blankCount: parsed.blankCount,
    parsed: parsed.labels.length,
  };
}
