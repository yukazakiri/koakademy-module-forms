import { Combobox, type ComboboxOption } from "@/components/ui/combobox";
import { Input } from "@/components/ui/input";
import {
  PHILIPPINE_CITIES_MUNICIPALITIES,
  PHILIPPINE_PROVINCES,
  PHILIPPINE_REGIONS,
} from "@/data/philippine-geography";
import { useMemo } from "react";

type ProfileLocationFieldKey =
  "ethnicity" | "region_of_origin" | "province_of_origin" | "city_of_origin";

type ProfileLocationField = {
  key: string;
  required: boolean;
  options: Record<string, string>;
  presentation?: { placeholder?: string };
};

type ProfileLocationFieldsProps = {
  field: ProfileLocationField;
  answers: Record<string, unknown>;
  onAnswersChange: (updates: Record<string, string>) => void;
};

function answer(
  answers: Record<string, unknown>,
  key: ProfileLocationFieldKey,
): string {
  return typeof answers[key] === "string" ? answers[key] : "";
}

export function PhilippineProfileLocationField({
  field,
  answers,
  onAnswersChange,
}: ProfileLocationFieldsProps) {
  const regionValue = answer(answers, "region_of_origin");
  const provinceValue = answer(answers, "province_of_origin");
  const cityValue = answer(answers, "city_of_origin");
  const selectedRegion = useMemo(
    () =>
      PHILIPPINE_REGIONS.find(
        (region) =>
          region.value === regionValue || region.label === regionValue,
      ) ?? null,
    [regionValue],
  );
  const provinces = useMemo(
    () =>
      selectedRegion
        ? PHILIPPINE_PROVINCES.filter(
            (province) => province.regionCode === selectedRegion.code,
          )
        : [],
    [selectedRegion],
  );
  const selectedProvince = useMemo(
    () => provinces.find((province) => province.name === provinceValue) ?? null,
    [provinceValue, provinces],
  );
  const directRegionCities = useMemo(
    () =>
      selectedRegion
        ? PHILIPPINE_CITIES_MUNICIPALITIES.filter(
            (city) =>
              city.regionCode === selectedRegion.code &&
              city.provinceCode === null,
          )
        : [],
    [selectedRegion],
  );
  const cities = useMemo(() => {
    if (selectedProvince) {
      return PHILIPPINE_CITIES_MUNICIPALITIES.filter(
        (city) => city.provinceCode === selectedProvince.code,
      );
    }

    if (provinceValue === "__direct_region__") {
      return directRegionCities;
    }

    return [];
  }, [directRegionCities, provinceValue, selectedProvince]);

  if (field.key === "ethnicity") {
    return (
      <Combobox
        options={Object.entries(field.options).map(([value, label]) => ({
          value,
          label,
        }))}
        value={answer(answers, "ethnicity")}
        onValueChange={(value) => onAnswersChange({ ethnicity: value })}
        placeholder={
          field.presentation?.placeholder ?? "Choose or add an ethnicity"
        }
        searchPlaceholder="Search Philippine ethnicities..."
        emptyText="No matching ethnicity."
        allowCreate
        createLabel="Use"
        required={field.required}
      />
    );
  }

  if (field.key === "region_of_origin") {
    return (
      <Combobox
        options={PHILIPPINE_REGIONS.map((region) => ({
          value: region.value,
          label: region.label,
          searchText: `${region.value} ${region.label}`,
        }))}
        value={regionValue}
        onValueChange={(value) => {
          onAnswersChange({
            region_of_origin: value,
            province_of_origin: "",
            city_of_origin: "",
          });
        }}
        placeholder={field.presentation?.placeholder ?? "Select a region"}
        searchPlaceholder="Search regions..."
        emptyText="No matching region."
        required={field.required}
      />
    );
  }

  if (field.key === "province_of_origin") {
    if (
      selectedRegion &&
      provinces.length === 0 &&
      directRegionCities.length === 0
    ) {
      return (
        <Input
          value="Not applicable"
          aria-label="Province of origin"
          disabled
        />
      );
    }

    return (
      <Combobox
        options={[
          ...provinces.map((province) => ({
            value: province.name,
            label: province.name,
          })),
          ...(directRegionCities.length > 0
            ? [
                {
                  value: "__direct_region__",
                  label: "No province (directly administered)",
                },
              ]
            : []),
        ]}
        value={provinceValue}
        onValueChange={(value) => {
          onAnswersChange({ province_of_origin: value, city_of_origin: "" });
        }}
        placeholder={
          selectedRegion ? "Select a province" : "Select a region first"
        }
        searchPlaceholder="Search provinces..."
        emptyText="No matching province."
        disabled={!selectedRegion}
        required={field.required}
      />
    );
  }

  return (
    <Combobox
      options={cities.map((city) => ({ value: city.name, label: city.name }))}
      value={cityValue}
      onValueChange={(value) => onAnswersChange({ city_of_origin: value })}
      placeholder={
        selectedRegion
          ? "Select a city or municipality"
          : "Select a region first"
      }
      searchPlaceholder="Search cities and municipalities..."
      emptyText="No matching city or municipality."
      disabled={
        !selectedRegion ||
        (!selectedProvince && provinceValue !== "__direct_region__")
      }
      required={field.required}
    />
  );
}
