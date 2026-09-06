import assert from "node:assert/strict";
import test from "node:test";
import { isFormFieldVisible } from "../../resources/assets/js/Pages/Forms/form-visibility.ts";

const parentIncome = {
  visibility: { field: "family_income_bracket", operator: "is_empty" },
};

test("separate parent ranges appear before choosing a family range", () => {
  assert.equal(isFormFieldVisible(parentIncome, {}), true);
  assert.equal(
    isFormFieldVisible(parentIncome, { family_income_bracket: null }),
    true,
  );
});

test("selecting and clearing a shared range hides and restores separate parent ranges", () => {
  const answers = { family_income_bracket: "below_250k" };
  assert.equal(isFormFieldVisible(parentIncome, answers), false);
  answers.family_income_bracket = "";
  assert.equal(isFormFieldVisible(parentIncome, answers), true);
});

test("existing conditional disability fields retain their visibility behavior", () => {
  const disability = {
    visibility: { field: "is_pwd", operator: "equals", value: "yes" },
  };
  assert.equal(isFormFieldVisible(disability, { is_pwd: "yes" }), true);
  assert.equal(isFormFieldVisible(disability, { is_pwd: "no" }), false);
  assert.equal(isFormFieldVisible({ visibility: null }, {}), true);
});
