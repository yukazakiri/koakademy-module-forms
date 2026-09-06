export function isFormFieldVisible(
  field: {
    visibility: { field?: string; operator?: string; value?: string } | null;
  },
  answers: Record<string, unknown>,
): boolean {
  if (!field.visibility?.field) return true;
  const actual = answers[field.visibility.field];
  const expected = field.visibility.value;
  if (field.visibility.operator === "is_empty")
    return (
      actual === undefined ||
      actual === null ||
      actual === "" ||
      (Array.isArray(actual) && actual.length === 0)
    );
  if (field.visibility.operator === "not_equals") return actual !== expected;
  if (field.visibility.operator === "contains")
    return Array.isArray(actual) && actual.includes(expected);
  return actual === expected;
}
