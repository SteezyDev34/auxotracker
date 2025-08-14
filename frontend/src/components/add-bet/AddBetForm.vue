<template>
  <div class="container mx-auto p-6 max-w-4xl">
    <h1 class="text-2xl font-bold mb-6 text-gray-800">
      {{ events.length > 1 ? 'Ajouter un pari combiné' : 'Ajouter un pari simple' }}
    </h1>
    <form @submit.prevent="submitForm" class="space-y-4 mb-4">
      <!-- Date du pari -->
      <div class="flex flex-col gap-2">
        <DatePicker 
          id="bet_date" 
          v-model="formData.bet_date" 
          dateFormat="dd/mm/yy" 
          :showIcon="true" 
          placeholder="Date du pari *"
          class="w-full"
          :class="{ 'p-invalid': errors.bet_date }"
        />
        <small v-if="errors.bet_date" class="text-red-500 block mt-1">{{ errors.bet_date }}</small>
      </div>

      <!-- Cards Événements -->
      <div v-for="(eventData, eventIndex) in eventCards" :key="eventData.id" class="border rounded-lg p-4 bg-gray-50 mb-4">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-800">Événement {{ eventIndex + 1 }}</h3>
          <Button 
            v-if="eventCards.length > 1"
            icon="pi pi-times" 
            class="p-button-text p-button-sm text-red-500"
            @click="removeEventCard(eventIndex)"
            aria-label="Supprimer cet événement"
          />
        </div>
        
        <!-- Sport -->
        <div class="flex flex-col gap-2 mb-4">
          <div class="relative">
            <AutoComplete 
              :id="`sport_${eventIndex}`" 
              v-model="eventData.selectedSport" 
              :suggestions="eventData.sportSearchResults || []" 
              @complete="(event) => searchSports(event, eventIndex)"
              @item-select="(event) => onSportSelect(event, eventIndex)"
              @dropdown-click="() => onSportDropdownShow(eventIndex)"
              optionLabel="name"
              :placeholder="eventData.selectedSport && eventData.selectedSport.length > 0 ? '' : 'Sport'"
              class="w-full max-w-full select-custom"
              :class="{ 'p-invalid': errors[`sport_id_${eventIndex}`] }"
              :loading="eventData.sportLoading"
              panelClass="select-panel-custom"
              @focus="() => searchSports({ query: '' }, eventIndex)"
              :minLength="0"
              selectionLimit="1"
              dropdown
              dropdownMode="blank"
              multiple
              display="chip"
              aria-label="Rechercher et sélectionner un sport"
              role="combobox"
              aria-expanded="false"
              aria-autocomplete="list"
            >
              <!-- Template pour afficher le sport sélectionné -->
              <template #chip="slotProps">
                <div class="flex items-center gap-2">
                  <!-- Icône du sport sélectionné -->
                  <img
                    v-if="slotProps.value && slotProps.value.img"
                    :src="`${apiBaseUrl}/storage/sport_icons/${slotProps.value.img.replace('.png', '.svg')}`"
                    :alt="slotProps.value.name"
                    class="w-4 h-4 object-contain filter brightness-0"
                    @error="$event.target.style.display='none'"
                  />
                  <div 
                    v-else-if="slotProps.value"
                    class="w-4 h-4 bg-gray-300 rounded-full flex items-center justify-center text-xs text-gray-600"
                  >
                    {{ slotProps.value.name ? slotProps.value.name.charAt(0).toUpperCase() : '?' }}
                  </div>
                  <!-- Nom du sport sélectionné -->
                  <span>{{ slotProps.value ? slotProps.value.name : '' }}</span>
                </div>
              </template>
              
              <!-- Template pour les options du dropdown -->
              <template #option="slotProps">
                <div class="flex items-center gap-2 truncate max-w-full" :title="slotProps.option.name">
                  <!-- Icône du sport -->
                  <img
                    v-if="slotProps.option.img"
                    :src="`${apiBaseUrl}/storage/sport_icons/${slotProps.option.img.replace('.png', '.svg')}`"
                    :alt="slotProps.option.name"
                    class="w-5 h-5 object-contain flex-shrink-0 filter brightness-0"
                    @error="$event.target.style.display='none'"
                  />
                  <div 
                    v-else
                    class="w-5 h-5 bg-gray-300 rounded-full flex items-center justify-center text-xs text-gray-600 flex-shrink-0"
                  >
                    {{ slotProps.option.name ? slotProps.option.name.charAt(0).toUpperCase() : '?' }}
                  </div>
                  <!-- Nom du sport -->
                  <span class="truncate">{{ slotProps.option.name }}</span>
                </div>
              </template>
            </AutoComplete>
          </div>
          <small v-if="errors[`sport_id_${eventIndex}`]" class="text-red-500 block mt-1">{{ errors[`sport_id_${eventIndex}`] }}</small>
        </div>

        <!-- Champs conditionnels selon le sport -->
        <div v-if="eventData.sport_id" class="space-y-4 mb-4">
          <!-- Pays -->
          <div class="flex flex-col gap-2">
            <div class="relative">
              <AutoComplete 
                :id="`country_${eventIndex}`" 
                v-model="eventData.selectedCountry" 
                :suggestions="eventData.countryFilteredResults || []" 
                @complete="(event) => searchCountries(event, eventIndex)"
                @item-select="(event) => onCountrySelect(event, eventIndex)"
                optionLabel="name"
                :placeholder="eventData.selectedCountry && eventData.selectedCountry.length > 0 ? '' : 'Pays'"
                class="w-full max-w-full select-custom"
                :class="{ 'p-invalid': errors[`country_id_${eventIndex}`] }"
                :minLength="0"
                dropdown
                dropdownMode="blank"
                multiple
                display="chip"
                aria-label="Rechercher et sélectionner un pays"
                role="combobox"
                aria-expanded="false"
                aria-autocomplete="list"
            >
                <!-- Template pour afficher le pays sélectionné avec son drapeau -->
                <template #chip="slotProps">
                  <div class="flex items-center gap-2">
                    <!-- Drapeau du pays sélectionné -->
                    <img 
                      v-if="slotProps.value && slotProps.value.id"
                      :src="`${apiBaseUrl}/storage/country_flags/${slotProps.value.id}.png`" 
                      :alt="slotProps.value.name"
                      class="w-4 h-4 rounded object-cover flex-shrink-0" 
                      @error="$event.target.style.display='none'"
                    />
                    <!-- Nom du pays sélectionné -->
                    <span>{{ slotProps.value ? slotProps.value.name : '' }}</span>
                  </div>
                </template>
                
                <!-- Template pour les options du dropdown -->
                <template #option="slotProps">
                  <div class="flex items-center gap-2 truncate max-w-full" :title="slotProps.option.name">
                    <!-- Drapeau du pays -->
                    <img 
                      v-if="slotProps.option.id"
                      :src="`${apiBaseUrl}/storage/country_flags/${slotProps.option.id}.png`" 
                      :alt="slotProps.option.name"
                      class="w-4 h-4 rounded object-cover flex-shrink-0" 
                      @error="$event.target.style.display='none'"
                    />
                    <!-- Nom du pays -->
                    <span class="truncate">{{ slotProps.option.name }}</span>
                  </div>
                </template>

              </AutoComplete>
            </div>
            <small v-if="errors.country_id" class="text-red-500 block mt-1">{{ errors.country_id }}</small>
          </div>

          <!-- Ligue -->
          <div class="flex flex-col gap-2">
            <div class="relative">
              <AutoComplete 
                :id="`league-${eventIndex}`" 
                v-model="eventData.selectedLeague" 
                :suggestions="eventData.leagueSearchResults" 
                @complete="(event) => searchLeagues(event, eventIndex)"
                @item-select="(event) => onLeagueSelect(event, eventIndex)"
                optionLabel="name"
                :placeholder="eventData.selectedLeague && eventData.selectedLeague.length > 0 ? '' : 'Ligue'"
                class="w-full max-w-full select-custom"
                :class="{ 'p-invalid': errors[`league-${eventIndex}`] }"
                :loading="eventData.leagueLoading"
                :disabled="!eventData.sport_id"
                panelClass="select-panel-custom"
                @show="() => onLeagueDropdownShow(eventIndex)"
                @focus="() => searchLeagues({ query: '' }, eventIndex)"
                :minLength="0"
                dropdown
                dropdownMode="blank"
                multiple
                display="chip"
                aria-label="Rechercher et sélectionner une ligue"
                role="combobox"
                aria-expanded="false"
                aria-autocomplete="list"
              >
                <!-- Template pour afficher la ligue sélectionnée avec son logo -->
                 <template #chip="slotProps">
                   <div class="flex items-center gap-2">
                     
                     <!-- Logo de la ligue -->
                     <img 
                       v-if="slotProps.value.id"
                       :src="`${apiBaseUrl}/storage/league_logos/${slotProps.value.id}.png`" 
                       :alt="slotProps.value.name"
                       class="w-4 h-4 rounded object-cover flex-shrink-0" 
                       @error="$event.target.style.display='none'"
                     />
                     <!-- Nom de la ligue -->
                     <span>{{ slotProps.value ? slotProps.value.name : '' }}</span>
                   </div>
                 </template>
                <template #option="slotProps">
                  <div class="flex items-center gap-2 truncate max-w-full" :title="slotProps.option.name">
                    <!-- Drapeau du pays -->
                    <img 
                       v-if="slotProps.option.country_id"
                       :src="`${apiBaseUrl}/storage/country_flags/${slotProps.option.country_id}.png`" 
                       :alt="slotProps.option.country?.name || 'Pays'"
                       class="w-4 h-4 rounded object-cover flex-shrink-0" 
                       @error="$event.target.style.display='none'"
                     />
                    <!-- Logo de la ligue -->
                    <img 
                      v-if="slotProps.option.img"
                      :src="`${apiBaseUrl}/storage/${slotProps.option.img}`" 
                      :alt="slotProps.option.name"
                      class="w-4 h-4 rounded object-cover flex-shrink-0" 
                      @error="$event.target.style.display='none'"
                    />
                    <!-- Nom de la ligue -->
                    <span class="truncate">{{ slotProps.option.name }}</span>
                  </div>
                </template>
                
                <template #footer v-if="leagueHasMore">
                  <div class="flex justify-center items-center p-2" v-if="leagueLoading">
                    <i class="pi pi-spin pi-spinner"></i>
                  </div>
                  <div class="text-center p-2 text-sm text-gray-500" v-else>
                    Faites défiler pour charger plus de résultats
                  </div>
                </template>
              </AutoComplete>
            </div>
            <small v-if="errors[`league-${eventIndex}`]" class="text-red-500 block mt-1">{{ errors[`league-${eventIndex}`] }}</small>
          </div>

          <!-- Équipes -->
          <div class="space-y-4">
            <!-- Équipe 1 -->
            <div class="flex flex-col gap-2">
              <div class="relative">
                <AutoComplete 
                  :id="`team1-${eventIndex}`" 
                  v-model="eventData.selectedTeam1" 
                  :suggestions="eventData.team1SearchResults" 
                  @complete="(event) => searchTeam1(event, eventIndex)"
                  @item-select="(event) => onTeam1Select(event, eventIndex)"
                  optionLabel="name"
                  :placeholder="eventData.selectedTeam1 && eventData.selectedTeam1.length > 0 ? '' : 'Équipe 1'"
                  class="w-full max-w-full select-custom"
                  :class="{ 'p-invalid': errors[`team1-${eventIndex}`] }"
                  :loading="eventData.team1Loading"
                  :disabled="!eventData.sport_id"
                  panelClass="select-panel-custom"
                  @show="() => onTeam1DropdownShow(eventIndex)"
                  @focus="() => searchTeam1({ query: '' }, eventIndex)"
                  :minLength="0"
                  dropdown
                  dropdownMode="blank"
                  multiple
                  display="chip"
                  aria-label="Rechercher et sélectionner l'équipe 1"
                  role="combobox"
                  aria-expanded="false"
                  aria-autocomplete="list"
                >
                  <!-- Template pour afficher l'équipe 1 sélectionnée avec son logo -->
                   <template #chip="slotProps">
                     <div class="flex items-center gap-2">
                       <!-- Logo de l'équipe -->
                       <img 
                         v-if="slotProps.value.id"
                         :src="`${apiBaseUrl}/storage/team_logos/${slotProps.value.id}.png`" 
                         :alt="slotProps.value.name"
                         class="w-4 h-4 rounded object-cover flex-shrink-0" 
                         @error="$event.target.style.display='none'"
                       />
                       <!-- Nom de l'équipe -->
                       <span>{{ slotProps.value ? slotProps.value.name : '' }}</span>
                     </div>
                   </template>
                  <template #option="slotProps">
                    <div class="flex items-center gap-2 truncate max-w-full" :title="slotProps.option.name">
                      <!-- Logo de l'équipe -->
                      <img 
                        v-if="slotProps.option.img"
                        :src="`${apiBaseUrl}/storage/${slotProps.option.img}`" 
                        :alt="slotProps.option.name"
                        class="w-4 h-4 rounded object-cover flex-shrink-0" 
                        @error="$event.target.style.display='none'"
                      />
                      <!-- Nom de l'équipe -->
                      <span class="truncate">{{ slotProps.option.name }}</span>
                      <span v-if="slotProps.option.league_name" class="text-sm text-gray-500 ml-2">
                        ({{ slotProps.option.league_name }})
                      </span>
                    </div>
                  </template>
                  
                  <template #footer v-if="team1HasMore">
                    <div class="flex justify-center items-center p-2" v-if="team1Loading">
                      <i class="pi pi-spin pi-spinner"></i>
                    </div>
                    <div class="text-center p-2 text-sm text-gray-500" v-else>
                      Faites défiler pour charger plus de résultats
                    </div>
                  </template>
                </AutoComplete>
              </div>
              <small v-if="errors[`team1-${eventIndex}`]" class="text-red-500 block mt-1">{{ errors[`team1-${eventIndex}`] }}</small>
            </div>
            
            <!-- Équipe 2 -->
            <div class="flex flex-col gap-2">
              <div class="relative">
                  <AutoComplete 
                    :id="`team2-${eventIndex}`" 
                    v-model="eventData.selectedTeam2" 
                    :suggestions="eventData.team2SearchResults" 
                    @complete="(event) => searchTeam2(event, eventIndex)"
                    @item-select="(event) => onTeam2Select(event, eventIndex)"
                    optionLabel="name"
                    :placeholder="eventData.selectedTeam2 && eventData.selectedTeam2.length > 0 ? '' : 'Équipe 2'"
                    class="w-full max-w-full select-custom"
                    :class="{ 'p-invalid': errors[`team2-${eventIndex}`] }"
                    :loading="eventData.team2Loading"
                    :disabled="!eventData.sport_id"
                    panelClass="select-panel-custom"
                    @show="() => onTeam2DropdownShow(eventIndex)"
                    @focus="() => searchTeam2({ query: '' }, eventIndex)"
                    :minLength="0"
                    dropdown
                    dropdownMode="blank"
                    multiple
                    display="chip"
                    aria-label="Rechercher et sélectionner l'équipe 2"
                    role="combobox"
                    aria-expanded="false"
                    aria-autocomplete="list"
                  >
                    <!-- Template pour afficher l'équipe 2 sélectionnée avec son logo -->
                     <template #chip="slotProps">
                       <div class="flex items-center gap-2">
                         <!-- Logo de l'équipe -->
                         <img 
                           v-if="slotProps.value.id"
                           :src="`${apiBaseUrl}/storage/team_logos/${slotProps.value.id}.png`" 
                           :alt="slotProps.value.name"
                           class="w-4 h-4 rounded object-cover flex-shrink-0" 
                           @error="$event.target.style.display='none'"
                         />
                         <!-- Nom de l'équipe -->
                         <span>{{ slotProps.value ? slotProps.value.name : '' }}</span>
                       </div>
                     </template>
                    <template #option="slotProps">
                      <div class="flex items-center gap-2 truncate max-w-full" :title="slotProps.option.name">
                        <!-- Logo de l'équipe -->
                        <img 
                          v-if="slotProps.option.img"
                          :src="`${apiBaseUrl}/storage/${slotProps.option.img}`" 
                          :alt="slotProps.option.name"
                          class="w-4 h-4 rounded object-cover flex-shrink-0" 
                          @error="$event.target.style.display='none'"
                        />
                        <!-- Nom de l'équipe -->
                        <span class="truncate">{{ slotProps.option.name }}</span>
                        <span v-if="slotProps.option.league_name" class="text-sm text-gray-500 ml-2">
                          ({{ slotProps.option.league_name }})
                        </span>
                      </div>
                    </template>
                    
                    <template #footer v-if="team2HasMore">
                      <div class="flex justify-center items-center p-2" v-if="team2Loading">
                        <i class="pi pi-spin pi-spinner"></i>
                      </div>
                      <div class="text-center p-2 text-sm text-gray-500" v-else>
                        Faites défiler pour charger plus de résultats
                      </div>
                    </template>
                  </AutoComplete>
                </div>
                <small v-if="errors[`team2-${eventIndex}`]" class="text-red-500 block mt-1">{{ errors[`team2-${eventIndex}`] }}</small>
              </div>
            </div>
        </div>
        
        <!-- Description de l'événement -->
        <div class="flex flex-col gap-2 mb-4">
          <InputText 
            :id="`event_description_${eventIndex}`" 
            v-model="eventData.description" 
            placeholder="Description de l'événement *"
            class="w-full"
            :class="{ 'p-invalid': errors[`event_description-${eventIndex}`] }"
          />
          <small v-if="errors[`event_description-${eventIndex}`]" class="text-red-500 block mt-1">{{ errors[`event_description-${eventIndex}`] }}</small>
        </div>

            <!-- Champs spécifiques à l'événement pour les paris combinés -->
            <div v-if="events.length > 0" class="space-y-4">
              <!-- Résultat de l'événement -->
              <div class="flex flex-col gap-2">
                <Select 
                  :id="`event_result_${eventIndex}`" 
                  v-model="eventData.result" 
                  :options="resultOptions" 
                  optionLabel="label" 
                  optionValue="value"
                  placeholder="Résultat de l'événement *"
                  class="w-full select-custom"
                  :class="{ 'p-invalid': errors[`event_result-${eventIndex}`] }"
                  panelClass="select-panel-custom"
                  aria-label="Sélectionner le résultat de l'événement"
                />
                <small v-if="errors[`event_result-${eventIndex}`]" class="text-red-500 block mt-1">{{ errors[`event_result-${eventIndex}`] }}</small>
              </div>

              <!-- Cote de l'événement -->
              <div class="flex flex-col gap-2">
                <InputText 
                  :id="`event_odds_${eventIndex}`" 
                  :ref="`eventOddsInput_${eventIndex}`"
                  v-model="eventData.odds" 
                  placeholder="Cote de l'événement *"
                  class="w-full"
                  :class="{ 'p-invalid': errors[`event_odds-${eventIndex}`] }"
                  type="text"
                  @input="(e) => handleEventOddsInput(e, eventIndex)"
                  @keypress="handleEventOddsKeypress"
                />
                <small v-if="errors[`event_odds-${eventIndex}`]" class="text-red-500 block mt-1">{{ errors[`event_odds-${eventIndex}`] }}</small>
              </div>
            </div>
          </div>



          <!-- Bouton Ajouter un pari combiné -->
          <div class="flex justify-center mt-4 mb-4">
            <Button 
              type="button" 
              label="Ajouter un pari combiné" 
              icon="pi pi-plus" 
              class="p-button-outlined p-button-sm"
              @click="addEventCard"
            />
          </div>
    </form>

      <!-- Liste des événements ajoutés -->
      <div v-if="events.length > 0" class="border rounded-lg p-4 bg-blue-50">
        <h3 class="text-lg font-semibold mb-4 text-blue-800">Événements du pari combiné ({{ events.length }})</h3>
        
        <div class="space-y-3">
          <div v-for="(event, index) in events" :key="event.id" class="bg-white p-3 rounded border">
            <div class="flex justify-between items-start">
              <div class="flex-1">
                <div class="text-sm font-medium text-gray-800 mb-1">
                  Événement {{ index + 1 }}
                </div>
                <div class="text-sm text-gray-600 mb-2">
                  {{ event.team1?.name }} vs {{ event.team2?.name }}
                </div>
                <div class="text-xs text-gray-500 mb-1">
                  {{ event.league?.name }}
                </div>
                <div class="text-sm text-gray-700 mb-1">
                  {{ event.description }}
                </div>
                <div class="flex gap-4 text-xs">
                  <span v-if="event.odds" class="text-green-600 font-medium">
                    Cote: {{ event.odds }}
                  </span>
                  <span v-if="event.result" class="font-medium" :class="{
                    'text-green-600': event.result === 'won',
                    'text-red-600': event.result === 'lost',
                    'text-yellow-600': event.result === 'pending',
                    'text-gray-600': event.result === 'void'
                  }">
                    Résultat: {{ resultOptions.find(r => r.value === event.result)?.label || event.result }}
                  </span>
                </div>
              </div>
              <Button 
                icon="pi pi-times" 
                class="p-button-text p-button-sm text-red-500"
                @click="removeEvent(index)"
                aria-label="Supprimer cet événement"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Cote, Mise et Type -->
      <div class="grid grid-cols-3 sm:grid-cols-4 gap-1 overflow-hidden">
        <!-- Cote -->
        <div class="flex flex-col justify-center min-w-0 w-full">
          <div class="w-full">
            <InputText 
              id="global_odds" 
              v-model="formData.global_odds" 
              type="text"
              placeholder="Cote"
              class="w-full text-xs"
              :class="{ 'p-invalid': errors.global_odds }"
              @input="handleOddsInput"
              @keypress="handleOddsKeypress"
            />
          </div>
          <small v-if="errors.global_odds" class="text-red-500 text-xs truncate">{{ errors.global_odds }}</small>
        </div>
        
        <!-- Mise -->
        <div class="flex flex-col justify-center min-w-0 w-full">
          <div class="w-full">
            <InputText 
              id="stake" 
              v-model="formData.stake" 
              type="text"
              :placeholder="betTypeValue === 'currency' ? 'Mise en €' : betTypeValue === 'percentage' ? 'Mise en %' : 'Mise'"
              class="w-full text-xs"
              :class="{ 'p-invalid': errors.stake }"
              @input="handleStakeInput"
              @keypress="handleStakeKeypress"
            />
          </div>
          <small v-if="errors.stake" class="text-red-500 text-xs truncate">{{ errors.stake }}</small>
        </div>

        <!-- Type de mise -->
        <div class="flex flex-col justify-center min-w-0 w-full">
          <div class="w-full flex items-center">
            <SelectButton 
              v-model="betTypeValue" 
              :options="betTypeOptions" 
              optionLabel="symbol" 
              optionValue="value"
              class="h-8 text-xs w-full"
            />
          </div>
        </div>

        <!-- Gain potentiel (desktop uniquement) -->
        <div class="hidden sm:flex flex-col justify-center min-w-0 w-full mt-4 mb-4">
          <div class="w-full">
            <div class="p-2 bg-gray-50 rounded border text-xs font-semibold text-green-600 h-8 flex items-center justify-center">
              {{ potentialWin.toFixed(2) }} €
            </div>
          </div>
          <small class="text-gray-500 text-xs text-center">Gain potentiel</small>
        </div>
      </div>

      <!-- Section détaillée du gain potentiel (mode pourcentage uniquement) -->
      <div v-if="betTypeValue === 'percentage'" class="flex flex-col gap-2 mb-4 mt-4">
        <div class="p-4 bg-gray-50 rounded border">
          <h4 class="text-sm font-semibold text-gray-800 mb-3">Détails du gain potentiel</h4>
          
          <!-- Capital actuel -->
          <div class="flex justify-between items-center mb-2">
            <span class="text-sm text-gray-600">Capital actuel :</span>
            <span class="text-sm font-medium">
              <i v-if="capitalLoading" class="pi pi-spin pi-spinner text-xs"></i>
              <span v-else>{{ currentCapital.toFixed(2) }} €</span>
            </span>
          </div>
          
          <!-- Mise calculée -->
          <div v-if="calculatedStake > 0" class="flex justify-between items-center mb-2">
            <span class="text-sm text-gray-600">Mise calculée ({{ formData.stake }}%) :</span>
            <span class="text-sm font-medium text-blue-600">{{ calculatedStake.toFixed(2) }} €</span>
          </div>
          
          <!-- Cote -->
          <div v-if="formData.global_odds" class="flex justify-between items-center mb-2">
            <span class="text-sm text-gray-600">Cote :</span>
            <span class="text-sm font-medium">{{ parseFloat(formData.global_odds).toFixed(2) }}</span>
          </div>
          
          <!-- Gain potentiel -->
          <div class="flex justify-between items-center pt-2 border-t border-gray-200">
            <span class="text-sm font-semibold text-gray-800">Gain potentiel :</span>
            <span class="text-lg font-bold text-green-600">{{ potentialWin.toFixed(2) }} €</span>
          </div>
        </div>
      </div>

      <!-- Gain potentiel simple (mode devise uniquement) -->
      <div v-if="betTypeValue === 'currency'" class="flex flex-col gap-2 mt-4 mb-4">
        <div class="p-3 bg-gray-50 rounded border text-lg font-semibold text-green-600 text-center">
          Gain potentiel : {{ potentialWin.toFixed(2) }} €
        </div>
      </div>

      <!-- Résultat (optionnel) -->
      <div class="flex flex-col sm:flex-row sm:items-center gap-2">
        <div class="flex-1">
          <Select 
            id="result" 
            v-model="formData.result" 
            :options="resultOptions" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Sélectionner un résultat"
            class="w-full"
          />
        </div>
      </div>

    <div class="flex justify-end gap-2 mt-4">
      <Button 
        label="Annuler" 
        icon="pi pi-times" 
        @click="closeDialog" 
        class="p-button-text"
      />
      <Button 
        label="Ajouter le pari" 
        icon="pi pi-check" 
        @click="submitForm" 
        :loading="loading"
        :disabled="!isFormValid"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue';
// Dialog import supprimé car ce n'est plus un Dialog
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import AutoComplete from 'primevue/autocomplete';
import { BetService } from '@/service/BetService';
import { SportService } from '@/service/SportService';
import { CountryService } from '@/service/CountryService';
import { useToast } from 'primevue/usetoast';

// Props
// Props supprimés car ce n'est plus un Dialog

// Emits
const emit = defineEmits(['bet-created']);

// Composables
const toast = useToast();

// Variables réactives
const loading = ref(false);
const sports = ref([]);
const countries = ref([]);
const allCountries = ref([]);
const errors = ref({});
const eventOddsInput = ref(null);
const availableLeagues = ref([]);
const availableTeams = ref([]);
const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';

// Variables pour la recherche de pays
const countrySearchQuery = ref('');
const countrySearchResults = ref([]);
const countryLoading = ref(false);
const countryCurrentPage = ref(1);
const countryHasMore = ref(false);
const selectedCountry = ref([]);

// Variables pour la recherche de ligues
const leagueSearchQuery = ref('');
const leagueSearchResults = ref([]);
const leagueLoading = ref(false);
const leagueCurrentPage = ref(1);
const leagueHasMore = ref(false);
const selectedLeague = ref([]);

// Variables pour la recherche d'équipes 1
const team1SearchQuery = ref('');
const team1SearchResults = ref([]);
const team1Loading = ref(false);
const team1CurrentPage = ref(1);
const team1HasMore = ref(false);
const selectedTeam1 = ref([]);

// Variables pour la recherche d'équipes 2
const team2SearchQuery = ref('');
const team2SearchResults = ref([]);
const team2Loading = ref(false);
const team2CurrentPage = ref(1);
const team2HasMore = ref(false);
const selectedTeam2 = ref([]);

// Variables pour la recherche de sports
const sportSearchQuery = ref('');
const sportSearchResults = ref([]);
const sportLoading = ref(false);
const selectedSport = ref([]);

// Variables pour le type de mise
const betTypeValue = ref('currency');
const betTypeOptions = ref([
  { symbol: '€', value: 'currency' },
  { symbol: '%', value: 'percentage' }
]);

// Variables pour le capital actuel
const currentCapital = ref(0);
const calculatedStake = ref(0);
const capitalLoading = ref(false);

// Variables pour les cards d'événements multiples
const eventCards = ref([
  {
    id: 1,
    sport_id: null,
    country_id: null,
    league: null,
    team1: null,
    team2: null,
    description: '',
    result: null,
    odds: null,
    selectedSport: [],
    selectedCountry: [],
    selectedLeague: [],
    selectedTeam1: [],
    selectedTeam2: [],
    sportSearchResults: [],
    sportLoading: false,
    countryFilteredResults: [],
    leagueSearchResults: [],
    leagueLoading: false,
    team1SearchResults: [],
    team1Loading: false,
    team2SearchResults: [],
    team2Loading: false
  }
]);

// Variables pour les événements multiples (paris combinés)
const events = ref([]);
const currentEvent = ref({
  sport_id: null,
  country_id: null,
  league: null,
  team1: null,
  team2: null,
  bet_code: '',
  description: '',
  result: null,
  odds: null
});

// Données du formulaire
const formData = ref({
  bet_date: new Date(),
  sport_id: null,
  country_id: null,
  league: null,
  team1: null,
  team2: null,
  global_odds: null,
  stake: null,
  result: 'void'
});



// Options pour le résultat
const resultOptions = [
  { label: 'Annulé', value: 'void' },
  { label: 'Gagné', value: 'won' },
  { label: 'Perdu', value: 'lost' }
];

// Computed
// Variable visible supprimée car ce n'est plus un Dialog

const potentialWin = computed(() => {
  let stake = 0;
  
  if (betTypeValue.value === 'percentage' && calculatedStake.value > 0) {
    // Utiliser la mise calculée en pourcentage
    stake = calculatedStake.value;
  } else if (betTypeValue.value === 'currency' && formData.value.stake) {
    // Utiliser la mise en devise
    stake = parseFloat(formData.value.stake);
  }
  
  if (stake > 0 && formData.value.global_odds) {
    return stake * parseFloat(formData.value.global_odds);
  }
  return 0;
});

// Afficher les champs sport conditionnels
const showSportFields = computed(() => {
  return formData.value.sport_id !== null;
});

const isFormValid = computed(() => {
  // Seuls les champs essentiels sont obligatoires
  return formData.value.bet_date &&
         formData.value.global_odds &&
         formData.value.stake;
});



// Méthodes
/**
 * Charger la liste des sports disponibles depuis l'API
 */
async function loadSports() {
  try {
    sports.value = await SportService.getSports();
  } catch (error) {
    console.error('Erreur lors du chargement des sports:', error);
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de charger les sports',
      life: 3000
    });
    // Fallback vers des sports statiques en cas d'erreur
    sports.value = [
      { id: 1, name: 'Football' },
      { id: 2, name: 'Basketball' },
      { id: 3, name: 'Tennis' },
      { id: 4, name: 'Hockey' },
      { id: 5, name: 'Baseball' },
      { id: 6, name: 'Volleyball' },
      { id: 7, name: 'Rugby' },
      { id: 8, name: 'Handball' }
    ];
  }
}

/**
 * Charger la liste des pays disponibles
 */
async function loadCountries() {
  try {
    const countryData = await CountryService.getCountries();
    // Utiliser les vrais IDs des pays depuis l'API
    const formattedCountries = countryData.map(country => ({
      id: country.id, // Utiliser le vrai ID du pays
      name: country.name,
      code: country.code
    }));
    
    countries.value = formattedCountries;
    allCountries.value = formattedCountries;
  } catch (error) {
    console.error('Erreur lors du chargement des pays:', error);
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de charger les pays',
      life: 3000
    });
    countries.value = [];
    allCountries.value = [];
  }
}

/**
 * Charger les ligues d'un sport spécifique
 */
async function loadLeaguesBySport(sportId) {
  try {
    availableLeagues.value = await SportService.getLeaguesBySport(sportId);
  } catch (error) {
    console.error('Erreur lors du chargement des ligues:', error);
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de charger les ligues',
      life: 3000
    });
    availableLeagues.value = [];
  }
}

/**
 * Charger les équipes d'un sport spécifique
 */
async function loadTeamsBySport(sportId) {
  try {
    availableTeams.value = await SportService.getTeamsBySport(sportId);
  } catch (error) {
    console.error('Erreur lors du chargement des équipes par sport:', error);
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de charger les équipes',
      life: 3000
    });
    availableTeams.value = [];
  }
}

/**
 * Charger les équipes d'une ligue spécifique
 */
async function loadTeamsByLeague(leagueId) {
  try {
    availableTeams.value = await SportService.getTeamsByLeague(leagueId);
  } catch (error) {
    console.error('Erreur lors du chargement des équipes par ligue:', error);
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de charger les équipes de la ligue',
      life: 3000
    });
    availableTeams.value = [];
  }
}

/**
 * Rechercher des sports avec filtrage côté client
 * @param {Object} event - Événement de recherche contenant la query
 * @param {number} eventIndex - Index de l'événement
 */
async function searchSports(event, eventIndex) {
  const eventData = eventCards.value[eventIndex];
  const query = event.query || '';
  
  console.log('🔍 searchSports appelée avec:', {
    query,
    eventIndex
  });
  
  // Initialiser les résultats si nécessaire
  if (!eventData.sportSearchResults) {
    eventData.sportSearchResults = [];
  }
  
  try {
    eventData.sportLoading = true;
    console.log('⏳ Début de la requête API sports...');
    
    // Récupérer tous les sports
    const allSportsData = await SportService.getSports();
    
    console.log('📡 Réponse API sports reçue:', {
      data: allSportsData,
      dataLength: allSportsData?.length
    });
    
    // Filtrer les sports selon la query
    if (!query.trim().length) {
      eventData.sportSearchResults = allSportsData || [];
    } else {
      eventData.sportSearchResults = (allSportsData || []).filter((sport) => {
        return sport.name.toLowerCase().includes(query.toLowerCase());
      });
    }
    
    console.log('📝 Résultats sports mis à jour pour événement', eventIndex);
    
    console.log('✅ searchSports terminée:', {
      totalResults: eventData.sportSearchResults.length,
      eventIndex
    });
    
  } catch (error) {
    console.error('❌ Erreur lors de la recherche des sports:', error);
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de rechercher les sports',
      life: 3000
    });
  } finally {
    eventData.sportLoading = false;
    console.log('🏁 searchSports: loading terminé');
  }
}

/**
 * Gérer la sélection d'un sport
 * @param {Object} event - Événement de sélection
 * @param {number} eventIndex - Index de l'événement
 */
async function onSportSelect(event, eventIndex) {
  const eventData = eventCards.value[eventIndex];
  
  // Remplacer l'élément existant par le nouveau sport sélectionné
  if (event.value) {
    eventData.selectedSport = [event.value]; // Remplacer par le nouveau sport
    eventData.sport_id = event.value.id;
    console.log('✅ Sport sélectionné pour événement', eventIndex, ':', event.value);
  } else {
    eventData.selectedSport = [];
    eventData.sport_id = null;
    console.log('✅ Sport désélectionné pour événement', eventIndex);
  }
  
  // Réinitialiser les champs liés au sport pour cette card
  eventData.country_id = null;
  eventData.league = null;
  eventData.team1 = null;
  eventData.team2 = null;
  
  // Réinitialiser la recherche de pays pour cette card
  eventData.selectedCountry = [];
  eventData.countryFilteredResults = [];
  
  // Réinitialiser la recherche de ligues pour cette card
  eventData.selectedLeague = [];
  eventData.leagueSearchResults = [];
  
  // Réinitialiser la recherche d'équipes pour cette card
  eventData.selectedTeam1 = [];
  eventData.team1SearchResults = [];
  eventData.selectedTeam2 = [];
  eventData.team2SearchResults = [];
  
  // Charger les équipes du sport sélectionné
  if (eventData.sport_id) {
    await loadTeamsBySport(eventData.sport_id);
    // Charger les premières ligues
    await searchLeagues({ query: '' }, eventIndex);
    // Charger les premières équipes pour les deux sélecteurs
    await searchTeam1({ query: '' }, eventIndex);
    await searchTeam2({ query: '' }, eventIndex);
  }
}

/**
 * Gérer le changement de sport (fonction de compatibilité)
 * @param {Object} event - Événement de changement
 * @param {number} eventIndex - Index de l'événement
 */
async function onSportChange(event, eventIndex) {
  // Cette fonction est maintenant gérée par onSportSelect
  console.log('⚠️ onSportChange appelée - redirection vers onSportSelect');
  await onSportSelect(event, eventIndex);
}

/**
 * Rechercher des pays avec filtrage côté client
 * @param {Object} event - Événement de recherche contenant la query
 * @param {number} eventIndex - Index de l'événement
 */
function searchCountries(event, eventIndex) {
  const query = event.query || '';
  const eventData = eventCards.value[eventIndex];
  
  setTimeout(() => {
    if (!query.trim().length) {
      eventData.countryFilteredResults = [...allCountries.value];
    } else {
      eventData.countryFilteredResults = allCountries.value.filter((country) => {
        return country.name.toLowerCase().includes(query.toLowerCase());
      });
    }
  }, 250);
}

/**
 * Gérer la sélection d'un pays
 * @param {Object} event - Événement de sélection contenant le pays
 */
/**
 * Gérer la sélection d'un pays
 * @param {Object} event - Événement de sélection
 * @param {number} eventIndex - Index de l'événement
 */
function onCountrySelect(event, eventIndex) {
  const eventData = eventCards.value[eventIndex];
  
  // Remplacer l'élément existant par le nouveau pays sélectionné
  if (event.value) {
    eventData.selectedCountry = [event.value]; // Remplacer par le nouveau pays
    eventData.country_id = event.value.id;
  } else {
    eventData.selectedCountry = [];
    eventData.country_id = null;
  }
  
  // Déclencher le changement de pays
  onCountryChange(eventIndex);
}

/**
 * Gérer le changement de pays
 * @param {number} eventIndex - Index de l'événement
 */
async function onCountryChange(eventIndex) {
  const eventData = eventCards.value[eventIndex];
  
  // Réinitialiser les champs liés aux ligues et équipes pour cette card
  eventData.league = null;
  eventData.team1 = null;
  eventData.team2 = null;
  
  // Réinitialiser la recherche de ligues pour cette card
  eventData.selectedLeague = [];
  eventData.leagueSearchResults = [];
  
  // Réinitialiser la recherche d'équipes pour cette card
  eventData.selectedTeam1 = [];
  eventData.team1SearchResults = [];
  
  eventData.selectedTeam2 = [];
  eventData.team2SearchResults = [];
  
  // Recharger les ligues avec le filtre de pays si un sport est sélectionné
  if (eventData.sport_id) {
    await searchLeagues({ query: '' }, eventIndex);
    await searchTeam1({ query: '' }, eventIndex);
    await searchTeam2({ query: '' }, eventIndex);
  }
}

/**
 * Rechercher des ligues avec pagination
 * @param {Object} event - Événement de recherche contenant la query
 * @param {number} eventIndex - Index de l'événement
 */
async function searchLeagues(event, eventIndex) {
  const eventData = eventCards.value[eventIndex];
  
  if (!eventData.sport_id) {
    console.log('❌ searchLeagues: Aucun sport sélectionné pour événement', eventIndex);
    return;
  }
  
  const query = event.query || '';
  console.log('🔍 searchLeagues appelée avec:', {
    query,
    sportId: eventData.sport_id,
    eventIndex
  });
  
  // Initialiser les résultats si nécessaire
  if (!eventData.leagueSearchResults) {
    eventData.leagueSearchResults = [];
  }
  
  try {
    eventData.leagueLoading = true;
    console.log('⏳ Début de la requête API...');
    
    const response = await SportService.searchLeaguesBySport(
      eventData.sport_id,
      query,
      1,
      30,
      eventData.country_id
    );
    
    console.log('📡 Réponse API reçue:', {
      data: response.data,
      dataLength: response.data?.length,
      hasMore: response.hasMore,
      pagination: response.pagination,
      fullResponse: response
    });
    
    eventData.leagueSearchResults = response.data;
    console.log('📝 Résultats ligues mis à jour pour événement', eventIndex);
    
    console.log('✅ searchLeagues terminée:', {
      totalResults: eventData.leagueSearchResults.length,
      eventIndex
    });
    
  } catch (error) {
    console.error('❌ Erreur lors de la recherche des ligues:', error);
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de rechercher les ligues',
      life: 3000
    });
  } finally {
    eventData.leagueLoading = false;
    console.log('🏁 searchLeagues: loading terminé');
  }
}

/**
 * Gérer la sélection d'une ligue
 * @param {Object} event - Événement de sélection contenant la ligue
 * @param {number} eventIndex - Index de l'événement
 */
async function onLeagueSelect(event, eventIndex) {
  const eventData = eventCards.value[eventIndex];
  
  // Remplacer l'élément existant par la nouvelle ligue sélectionnée
  if (event.value) {
    eventData.selectedLeague = [event.value]; // Remplacer par la nouvelle ligue
    eventData.league = event.value.id;
  } else {
    eventData.selectedLeague = [];
    eventData.league = null;
  }
  
  // Réinitialiser les équipes sélectionnées
  eventData.team1 = null;
  eventData.team2 = null;
  eventData.selectedTeam1 = [];
  eventData.selectedTeam2 = [];
  
  // Recharger les équipes avec le filtre de ligue pour les deux sélecteurs
  await searchTeam1({ query: eventData.team1SearchQuery || '' }, eventIndex, true);
  await searchTeam2({ query: eventData.team2SearchQuery || '' }, eventIndex, true);
}

/**
 * Rechercher des équipes pour l'équipe 1 avec pagination et exclusion de l'équipe 2
 * @param {Object} event - Événement de recherche contenant la query
 * @param {number} eventIndex - Index de l'événement
 * @param {boolean} resetSearch - Forcer la réinitialisation de la recherche
 */
async function searchTeam1(event, eventIndex, resetSearch = false) {
  const eventData = eventCards.value[eventIndex];
  
  if (!eventData.sport_id) {
    console.log('❌ searchTeam1: Aucun sport sélectionné pour événement', eventIndex);
    return;
  }
  
  const query = event.query || '';
  console.log('🔍 searchTeam1 appelée avec:', {
    query,
    sportId: eventData.sport_id,
    leagueId: eventData.league,
    excludeTeam: eventData.team2,
    eventIndex,
    resetSearch
  });
  
  // Initialiser les résultats si nécessaire
  if (!eventData.team1SearchResults || resetSearch) {
    console.log('🔄 Initialisation recherche équipe 1 pour événement', eventIndex);
    eventData.team1SearchResults = [];
  }
  
  try {
    eventData.team1Loading = true;
    console.log('⏳ Début de la requête API équipes 1...');
    
    const response = await SportService.searchTeamsBySport(
      eventData.sport_id,
      query,
      1,
      30,
      eventData.league, // Filtrer par ligue si sélectionnée
      eventData.country_id // Filtrer par pays si sélectionné
    );
    
    console.log('📡 Réponse API équipes 1 reçue:', {
      data: response.data,
      dataLength: response.data?.length,
      hasMore: response.hasMore,
      pagination: response.pagination
    });
    
    // Filtrer pour exclure l'équipe 2 si elle est sélectionnée
    let filteredData = response.data;
    if (eventData.team2) {
      filteredData = response.data.filter(team => team.id !== eventData.team2);
      console.log('🚫 Équipe 2 exclue des résultats équipe 1:', {
        originalCount: response.data.length,
        filteredCount: filteredData.length,
        excludedTeamId: eventData.team2
      });
    }
    
    eventData.team1SearchResults = filteredData;
    console.log('📝 Résultats équipes 1 mis à jour pour événement', eventIndex);
    
    console.log('✅ searchTeam1 terminée:', {
      totalResults: eventData.team1SearchResults.length,
      eventIndex
    });
    
  } catch (error) {
    console.error('❌ Erreur lors de la recherche des équipes 1:', error);
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de rechercher les équipes',
      life: 3000
    });
  } finally {
    eventData.team1Loading = false;
    console.log('🏁 searchTeam1: loading terminé');
  }
}

/**
 * Rechercher des équipes pour l'équipe 2 avec pagination et exclusion de l'équipe 1
 * @param {Object} event - Événement de recherche contenant la query
 * @param {number} eventIndex - Index de l'événement
 * @param {boolean} resetSearch - Forcer la réinitialisation de la recherche
 */
async function searchTeam2(event, eventIndex, resetSearch = false) {
  const eventData = eventCards.value[eventIndex];
  
  if (!eventData.sport_id) {
    console.log('❌ searchTeam2: Aucun sport sélectionné pour événement', eventIndex);
    return;
  }
  
  const query = event.query || '';
  console.log('🔍 searchTeam2 appelée avec:', {
    query,
    sportId: eventData.sport_id,
    leagueId: eventData.league,
    excludeTeam: eventData.team1,
    eventIndex,
    resetSearch
  });
  
  // Initialiser les résultats si nécessaire
  if (!eventData.team2SearchResults || resetSearch) {
    console.log('🔄 Initialisation recherche équipe 2 pour événement', eventIndex);
    eventData.team2SearchResults = [];
  }
  
  try {
    eventData.team2Loading = true;
    console.log('⏳ Début de la requête API équipes 2...');
    
    const response = await SportService.searchTeamsBySport(
      eventData.sport_id,
      query,
      1,
      30,
      eventData.league, // Filtrer par ligue si sélectionnée
      eventData.country_id // Filtrer par pays si sélectionné
    );
    
    console.log('📡 Réponse API équipes 2 reçue:', {
      data: response.data,
      dataLength: response.data?.length,
      hasMore: response.hasMore,
      pagination: response.pagination
    });
    
    // Filtrer pour exclure l'équipe 1 si elle est sélectionnée
    let filteredData = response.data;
    if (eventData.team1) {
      filteredData = response.data.filter(team => team.id !== eventData.team1);
      console.log('🚫 Équipe 1 exclue des résultats équipe 2:', {
        originalCount: response.data.length,
        filteredCount: filteredData.length,
        excludedTeamId: eventData.team1
      });
    }
    
    eventData.team2SearchResults = filteredData;
    console.log('📝 Résultats équipes 2 mis à jour pour événement', eventIndex);
    
    console.log('✅ searchTeam2 terminée:', {
      totalResults: eventData.team2SearchResults.length,
      eventIndex
    });
    
  } catch (error) {
    console.error('❌ Erreur lors de la recherche des équipes 2:', error);
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de rechercher les équipes',
      life: 3000
    });
  } finally {
    eventData.team2Loading = false;
    console.log('🏁 searchTeam2: loading terminé');
  }
}

/**
 * Gérer la sélection de l'équipe 1
 * @param {Object} event - Événement de sélection contenant l'équipe
 * @param {number} eventIndex - Index de l'événement
 */
function onTeam1Select(event, eventIndex) {
  const eventData = eventCards.value[eventIndex];
  
  // Remplacer l'élément existant par la nouvelle équipe sélectionnée
  if (event.value) {
    eventData.selectedTeam1 = [event.value]; // Remplacer par la nouvelle équipe
    eventData.team1 = event.value.id;
    console.log('✅ Équipe 1 sélectionnée pour événement', eventIndex, ':', event.value);
  } else {
    eventData.selectedTeam1 = [];
    eventData.team1 = null;
    console.log('✅ Équipe 1 désélectionnée pour événement', eventIndex);
  }
  
  // Rafraîchir les résultats de l'équipe 2 pour exclure l'équipe 1 sélectionnée
  if (eventData.team2SearchResults && eventData.team2SearchResults.length > 0) {
    console.log('🔄 Rafraîchissement des résultats équipe 2 pour exclure équipe 1');
    searchTeam2({ query: eventData.team2SearchQuery || '' }, eventIndex, true);
  }
}

/**
 * Gérer la sélection de l'équipe 2
 * @param {Object} event - Événement de sélection contenant l'équipe
 * @param {number} eventIndex - Index de l'événement
 */
function onTeam2Select(event, eventIndex) {
  const eventData = eventCards.value[eventIndex];
  
  // Remplacer l'élément existant par la nouvelle équipe sélectionnée
  if (event.value) {
    eventData.selectedTeam2 = [event.value]; // Remplacer par la nouvelle équipe
    eventData.team2 = event.value.id;
    console.log('✅ Équipe 2 sélectionnée pour événement', eventIndex, ':', event.value);
  } else {
    eventData.selectedTeam2 = [];
    eventData.team2 = null;
    console.log('✅ Équipe 2 désélectionnée pour événement', eventIndex);
  }
  
  // Rafraîchir les résultats de l'équipe 1 pour exclure l'équipe 2 sélectionnée
  if (eventData.team1SearchResults && eventData.team1SearchResults.length > 0) {
    console.log('🔄 Rafraîchissement des résultats équipe 1 pour exclure équipe 2');
    searchTeam1({ query: eventData.team1SearchQuery || '' }, eventIndex, true);
  }
}

/**
 * Charger plus d'équipes 1 (pagination)
 */
async function loadMoreTeam1() {
  if (team1Loading.value || !team1HasMore.value) {
    console.log('⏸️ loadMoreTeam1: Chargement en cours ou plus de résultats');
    return;
  }
  
  console.log('📄 Chargement de la page suivante équipes 1:', team1CurrentPage.value + 1);
  team1CurrentPage.value++;
  searchTeam1({ query: team1SearchQuery.value });
}

/**
 * Charger plus d'équipes 2 (pagination)
 */
async function loadMoreTeam2() {
  if (team2Loading.value || !team2HasMore.value) {
    console.log('⏸️ loadMoreTeam2: Chargement en cours ou plus de résultats');
    return;
  }
  
  console.log('📄 Chargement de la page suivante équipes 2:', team2CurrentPage.value + 1);
  team2CurrentPage.value++;
  searchTeam2({ query: team2SearchQuery.value });
}



/**
 * Gérer l'affichage du dropdown des équipes 1
 * @param {number} eventIndex - Index de l'événement
 */
function onTeam1DropdownShow(eventIndex) {
  console.log('🔽 Dropdown équipes 1 ouvert pour événement', eventIndex);
  const eventData = eventCards.value[eventIndex];
  if ((!eventData.team1SearchResults || eventData.team1SearchResults.length === 0) && eventData.sport_id) {
    console.log('🔄 Chargement initial des équipes 1 au dropdown');
    searchTeam1({ query: '' }, eventIndex, true);
  }
}

/**
 * Gérer l'affichage du dropdown des équipes 2
 * @param {number} eventIndex - Index de l'événement
 */
function onTeam2DropdownShow(eventIndex) {
  console.log('🔽 Dropdown équipes 2 ouvert pour événement', eventIndex);
  const eventData = eventCards.value[eventIndex];
  if ((!eventData.team2SearchResults || eventData.team2SearchResults.length === 0) && eventData.sport_id) {
    console.log('🔄 Chargement initial des équipes 2 au dropdown');
    searchTeam2({ query: '' }, eventIndex, true);
  }
}

/**
 * Gérer l'affichage du dropdown des sports
 * @param {number} eventIndex - Index de l'événement
 */
function onSportDropdownShow(eventIndex) {
  console.log('🔽 Dropdown sports ouvert pour événement', eventIndex);
  
  // Charger les sports si pas encore chargés pour cette card
  const eventData = eventCards.value[eventIndex];
  if (!eventData.sportSearchResults || eventData.sportSearchResults.length === 0) {
    searchSports({ query: '' }, eventIndex);
  }
}

/**
 * Gérer l'affichage du dropdown des ligues
 * @param {number} eventIndex - Index de l'événement
 */
function onLeagueDropdownShow(eventIndex) {
  console.log('🔽 Dropdown ligues ouvert pour événement', eventIndex);
  
  // Charger les ligues si pas encore chargées pour cette card
  const eventData = eventCards.value[eventIndex];
  if (!eventData.leagueSearchResults || eventData.leagueSearchResults.length === 0) {
    searchLeagues({ query: '' }, eventIndex);
  }
}



/**
 * Gérer le défilement du panel équipes 1 pour le lazy loading
 * @param {Event} event - Événement de défilement
 */
function handleTeam1PanelScroll(event) {
  const panel = event.target;
  const scrollTop = panel.scrollTop;
  const scrollHeight = panel.scrollHeight;
  const clientHeight = panel.clientHeight;
  
  // Calculer le pourcentage de défilement
  const scrollPercentage = (scrollTop + clientHeight) / scrollHeight;
  
  console.log('📊 Scroll équipes 1 détecté:', {
    scrollTop,
    scrollHeight,
    clientHeight,
    scrollPercentage: Math.round(scrollPercentage * 100) + '%',
    hasMore: team1HasMore.value,
    loading: team1Loading.value,
    currentPage: team1CurrentPage.value,
    resultsCount: team1SearchResults.value.length
  });
  
  // Si on a atteint 90% du défilement et qu'il y a plus de données
  if (scrollPercentage >= 0.9) {
    console.log('🎯 90% atteint pour équipes 1! État actuel:', {
      hasMore: team1HasMore.value,
      loading: team1Loading.value,
      willTrigger: team1HasMore.value && !team1Loading.value
    });
    
    if (team1HasMore.value && !team1Loading.value) {
      console.log('🚀 Déclenchement du lazy loading équipes 1...');
      loadMoreTeam1();
    } else {
      console.log('❌ Lazy loading équipes 1 non déclenché:', {
        reason: !team1HasMore.value ? 'Pas de données supplémentaires' : 'Chargement en cours'
      });
    }
  }
}

/**
 * Gérer le défilement du panel équipes 2 pour le lazy loading
 * @param {Event} event - Événement de défilement
 */
function handleTeam2PanelScroll(event) {
  const panel = event.target;
  const scrollTop = panel.scrollTop;
  const scrollHeight = panel.scrollHeight;
  const clientHeight = panel.clientHeight;
  
  // Calculer le pourcentage de défilement
  const scrollPercentage = (scrollTop + clientHeight) / scrollHeight;
  
  console.log('📊 Scroll équipes 2 détecté:', {
    scrollTop,
    scrollHeight,
    clientHeight,
    scrollPercentage: Math.round(scrollPercentage * 100) + '%',
    hasMore: team2HasMore.value,
    loading: team2Loading.value,
    currentPage: team2CurrentPage.value,
    resultsCount: team2SearchResults.value.length
  });
  
  // Si on a atteint 90% du défilement et qu'il y a plus de données
  if (scrollPercentage >= 0.9) {
    console.log('🎯 90% atteint pour équipes 2! État actuel:', {
      hasMore: team2HasMore.value,
      loading: team2Loading.value,
      willTrigger: team2HasMore.value && !team2Loading.value
    });
    
    if (team2HasMore.value && !team2Loading.value) {
      console.log('🚀 Déclenchement du lazy loading équipes 2...');
      loadMoreTeam2();
    } else {
      console.log('❌ Lazy loading équipes 2 non déclenché:', {
        reason: !team2HasMore.value ? 'Pas de données supplémentaires' : 'Chargement en cours'
      });
    }
  }
}

/**
 * Gérer la saisie de la cote pour remplacer immédiatement les virgules par des points
 * @param {Event} event - Événement d'input
 */
function handleOddsInput(event) {
  let inputValue = event.target.value;
  console.log('handleOddsInput - Valeur tapée:', inputValue);
  
  // Remplacer immédiatement toutes les virgules par des points
  const normalizedValue = inputValue.replace(/,/g, '.');
  console.log('handleOddsInput - Valeur normalisée:', normalizedValue);
  
  // Si une virgule a été détectée, forcer le remplacement immédiat
  if (inputValue !== normalizedValue) {
    console.log('handleOddsInput - Virgule détectée, remplacement en cours...');
    // Sauvegarder la position du curseur
    const cursorPosition = event.target.selectionStart;
    
    // Mettre à jour immédiatement la valeur de l'input
    event.target.value = normalizedValue;
    
    // Restaurer la position du curseur
    event.target.setSelectionRange(cursorPosition, cursorPosition);
    
    // Mettre à jour le v-model
    formData.value.global_odds = normalizedValue;
    console.log('handleOddsInput - Remplacement terminé, nouvelle valeur:', event.target.value);
    return;
  }
  
  // Vérifier que la valeur est un nombre réel valide
  if (normalizedValue === '' || normalizedValue === '.') {
    formData.value.global_odds = null;
    return;
  }
  
  // Validation du format nombre réel
  const numericValue = parseFloat(normalizedValue);
  if (!isNaN(numericValue) && isFinite(numericValue) && numericValue > 0) {
    formData.value.global_odds = numericValue;
  } else {
    // Si la valeur n'est pas valide, on garde la dernière valeur valide
    console.warn('Valeur de cote invalide:', normalizedValue);
  }
}

/**
 * Gérer la saisie de la mise pour accepter les virgules et les points comme séparateurs décimaux
 * @param {Event} event - Événement d'input
 */
function handleStakeInput(event) {
  let inputValue = event.target.value;
  console.log('handleStakeInput - Valeur tapée:', inputValue);
  
  // Remplacer immédiatement toutes les virgules par des points
  const normalizedValue = inputValue.replace(/,/g, '.');
  console.log('handleStakeInput - Valeur normalisée:', normalizedValue);
  
  // Si une virgule a été détectée, forcer le remplacement immédiat
  if (inputValue !== normalizedValue) {
    console.log('handleStakeInput - Virgule détectée, remplacement en cours...');
    // Sauvegarder la position du curseur
    const cursorPosition = event.target.selectionStart;
    
    // Mettre à jour immédiatement la valeur de l'input
    event.target.value = normalizedValue;
    
    // Restaurer la position du curseur
    event.target.setSelectionRange(cursorPosition, cursorPosition);
    
    // Mettre à jour le v-model
    formData.value.stake = normalizedValue;
    console.log('handleStakeInput - Remplacement terminé, nouvelle valeur:', event.target.value);
    return;
  }
  
  // Vérifier que la valeur est un nombre réel valide
  if (normalizedValue === '' || normalizedValue === '.') {
    formData.value.stake = null;
    return;
  }
  
  // Validation du format nombre réel (la mise peut être 0)
  const numericValue = parseFloat(normalizedValue);
  if (!isNaN(numericValue) && isFinite(numericValue) && numericValue >= 0) {
    formData.value.stake = numericValue;
  } else {
    // Si la valeur n'est pas valide, on garde la dernière valeur valide
    console.warn('Valeur de mise invalide:', normalizedValue);
  }
}

/**
 * Gérer la saisie de la cote d'événement pour remplacer immédiatement les virgules par des points
 * @param {Event} event - Événement d'input
 * @param {number} eventIndex - Index de l'événement
 */
function handleEventOddsInput(event, eventIndex) {
  let inputValue = event.target.value;
  console.log('handleEventOddsInput - Valeur tapée:', inputValue, 'pour événement', eventIndex);
  
  const eventData = eventCards.value[eventIndex];
  
  // Remplacer immédiatement toutes les virgules par des points
  const normalizedValue = inputValue.replace(/,/g, '.');
  console.log('handleEventOddsInput - Valeur normalisée:', normalizedValue);
  
  // Si une virgule a été détectée, forcer le remplacement immédiat
  if (inputValue !== normalizedValue) {
    console.log('handleEventOddsInput - Virgule détectée, remplacement en cours...');
    // Sauvegarder la position du curseur
    const cursorPosition = event.target.selectionStart;
    
    // Mettre à jour immédiatement la valeur de l'input
    event.target.value = normalizedValue;
    
    // Restaurer la position du curseur
    event.target.setSelectionRange(cursorPosition, cursorPosition);
    
    // Mettre à jour le v-model
    eventData.odds = normalizedValue;
    console.log('handleEventOddsInput - Remplacement terminé, nouvelle valeur:', event.target.value);
    return;
  }
  
  // Vérifier que la valeur est un nombre réel valide
  if (normalizedValue === '' || normalizedValue === '.') {
    eventData.odds = null;
    // Recalculer la cote globale même avec une valeur vide
    calculateGlobalOdds();
    return;
  }
  
  // Validation du format nombre réel
  const numericValue = parseFloat(normalizedValue);
  if (!isNaN(numericValue) && isFinite(numericValue) && numericValue > 0) {
    eventData.odds = numericValue;
  } else {
    // Si la valeur n'est pas valide, on garde la dernière valeur valide
    console.warn('Valeur de cote d\'événement invalide:', normalizedValue);
  }
  
  // Recalculer la cote globale
  calculateGlobalOdds();
}

/**
 * Gérer les touches pressées pour la cote globale (permettre point et virgule)
 * @param {KeyboardEvent} event - Événement de frappe
 */
function handleOddsKeypress(event) {
  const char = String.fromCharCode(event.which);
  const currentValue = event.target.value;
  
  // Permettre les chiffres, le point, la virgule et les touches de contrôle
  if (!/[0-9.,]/.test(char) && event.which !== 8 && event.which !== 46 && event.which !== 37 && event.which !== 39) {
    event.preventDefault();
    return;
  }
  
  // Empêcher plusieurs séparateurs décimaux (point ou virgule)
  if ((char === '.' || char === ',') && (currentValue.includes('.') || currentValue.includes(','))) {
    event.preventDefault();
    return;
  }
  
  // Empêcher le point/virgule en première position
  if ((char === '.' || char === ',') && currentValue === '') {
    event.preventDefault();
    return;
  }
}

/**
 * Gérer les touches pressées pour la mise (permettre point et virgule)
 * @param {KeyboardEvent} event - Événement de frappe
 */
function handleStakeKeypress(event) {
  const char = String.fromCharCode(event.which);
  const currentValue = event.target.value;
  
  // Permettre les chiffres, le point, la virgule et les touches de contrôle
  if (!/[0-9.,]/.test(char) && event.which !== 8 && event.which !== 46 && event.which !== 37 && event.which !== 39) {
    event.preventDefault();
    return;
  }
  
  // Empêcher plusieurs séparateurs décimaux (point ou virgule)
  if ((char === '.' || char === ',') && (currentValue.includes('.') || currentValue.includes(','))) {
    event.preventDefault();
    return;
  }
  
  // Empêcher le point/virgule en première position
  if ((char === '.' || char === ',') && currentValue === '') {
    event.preventDefault();
    return;
  }
}

/**
 * Gérer les touches pressées pour la cote d'événement (permettre point et virgule)
 * @param {KeyboardEvent} event - Événement de frappe
 */
function handleEventOddsKeypress(event) {
  const char = String.fromCharCode(event.which);
  const currentValue = event.target.value;
  
  // Permettre les chiffres, le point, la virgule et les touches de contrôle
  if (!/[0-9.,]/.test(char) && event.which !== 8 && event.which !== 46 && event.which !== 37 && event.which !== 39) {
    event.preventDefault();
    return;
  }
  
  // Empêcher plusieurs séparateurs décimaux (point ou virgule)
  if ((char === '.' || char === ',') && (currentValue.includes('.') || currentValue.includes(','))) {
    event.preventDefault();
    return;
  }
  
  // Empêcher le point/virgule en première position
  if ((char === '.' || char === ',') && currentValue === '') {
    event.preventDefault();
    return;
  }
}



/**
 * Gérer l'ouverture du dropdown pour attacher le scroll listener
 */
function onDropdownShow() {
  console.log('🔍 Dropdown ouvert, recherche du panel...');
  
  // Fonction pour rechercher le panel
  const findAndAttachListener = () => {
    // Utiliser le bon sélecteur basé sur la structure DOM observée
    const panel = document.querySelector('.p-autocomplete-list-container');
    console.log('🔍 Panel trouvé (.p-autocomplete-list-container):', panel);
    
    if (panel && !panel.hasScrollListener) {
      panel.hasScrollListener = true;
      panel.addEventListener('scroll', handlePanelScroll);
      console.log('✅ Scroll listener attaché au panel');
      return true;
    } else if (panel && panel.hasScrollListener) {
      console.log('⚠️ Scroll listener déjà attaché');
      return true;
    } else {
      console.log('❌ Aucun panel trouvé avec les sélecteurs testés');
      return false;
    }
  };
  
  // Essayer immédiatement avec nextTick
  nextTick(() => {
    if (!findAndAttachListener()) {
      // Si pas trouvé, essayer avec un délai
      console.log('⏰ Nouvelle tentative dans 100ms...');
      setTimeout(() => {
        if (!findAndAttachListener()) {
          console.log('⏰ Dernière tentative dans 300ms...');
          setTimeout(findAndAttachListener, 300);
        }
      }, 100);
    }
  });
}



/**
 * Charger plus de ligues (pagination)
 */
async function loadMoreLeagues() {
  console.log('🚀 loadMoreLeagues appelée avec état:', {
    sportId: formData.value.sport_id,
    hasMore: leagueHasMore.value,
    loading: leagueLoading.value,
    currentPage: leagueCurrentPage.value,
    query: leagueSearchQuery.value,
    currentResultsCount: leagueSearchResults.value.length
  });
  
  if (!formData.value.sport_id || !leagueHasMore.value || leagueLoading.value) {
    console.log('❌ loadMoreLeagues bloquée:', {
      noSport: !formData.value.sport_id,
      noMore: !leagueHasMore.value,
      alreadyLoading: leagueLoading.value
    });
    return;
  }
  
  try {
    leagueLoading.value = true;
    leagueCurrentPage.value++;
    
    console.log('🚀 Chargement page', leagueCurrentPage.value, 'pour query:', leagueSearchQuery.value);
    
    const response = await SportService.searchLeaguesBySport(
      formData.value.sport_id,
      leagueSearchQuery.value,
      leagueCurrentPage.value,
      30,
      formData.value.country_id
    );
    
    console.log('📡 loadMoreLeagues - Réponse API:', {
      data: response.data,
      dataLength: response.data?.length,
      hasMore: response.hasMore,
      pagination: response.pagination,
      fullResponse: response
    });
    
    // Ajouter les nouveaux résultats à la liste existante
    const previousCount = leagueSearchResults.value.length;
    leagueSearchResults.value = [...leagueSearchResults.value, ...response.data];
    leagueHasMore.value = response.hasMore;
    
    console.log('✅ Page chargée:', {
      newLeagues: response.data.length,
      previousTotal: previousCount,
      newTotal: leagueSearchResults.value.length,
      hasMoreAfter: leagueHasMore.value
    });
    
  } catch (error) {
    console.error('❌ Erreur lors du chargement de plus de ligues:', error);
    // Revenir à la page précédente en cas d'erreur
    leagueCurrentPage.value--;
    console.log('🔄 Page remise à:', leagueCurrentPage.value);
  } finally {
    leagueLoading.value = false;
    console.log('🏁 loadMoreLeagues: loading terminé');
  }
}

/**
 * Gérer le défilement du panneau pour le lazy loading
 * @param {Event} event - Événement de défilement
 */
function handlePanelScroll(event) {
  const panel = event.target;
  const scrollTop = panel.scrollTop;
  const scrollHeight = panel.scrollHeight;
  const clientHeight = panel.clientHeight;
  
  // Calculer le pourcentage de défilement
  const scrollPercentage = (scrollTop + clientHeight) / scrollHeight;
  
  console.log('📊 Scroll détecté:', {
    scrollTop,
    scrollHeight,
    clientHeight,
    scrollPercentage: Math.round(scrollPercentage * 100) + '%',
    hasMore: leagueHasMore.value,
    loading: leagueLoading.value,
    currentPage: leagueCurrentPage.value,
    resultsCount: leagueSearchResults.value.length
  });
  
  // Si on a atteint 90% du défilement et qu'il y a plus de données
  if (scrollPercentage >= 0.9) {
    console.log('🎯 90% atteint! État actuel:', {
      hasMore: leagueHasMore.value,
      loading: leagueLoading.value,
      willTrigger: leagueHasMore.value && !leagueLoading.value
    });
    
    if (leagueHasMore.value && !leagueLoading.value) {
      console.log('🚀 Déclenchement du lazy loading...');
      loadMoreLeagues();
    } else {
      console.log('❌ Lazy loading non déclenché:', {
        reason: !leagueHasMore.value ? 'Pas de données supplémentaires' : 'Chargement en cours'
      });
    }
  }
}

/**
 * Gérer le changement de ligue (méthode legacy, gardée pour compatibilité)
 */
async function onLeagueChange() {
  // Réinitialiser les équipes sélectionnées
  formData.value.team1 = null;
  formData.value.team2 = null;
  
  // Si une ligue est sélectionnée, charger ses équipes
  if (formData.value.league) {
    await loadTeamsByLeague(formData.value.league);
  } else if (formData.value.sport_id) {
    // Sinon, charger toutes les équipes du sport
    await loadTeamsBySport(formData.value.sport_id);
  }
}

/**
 * Valider le formulaire
 */
function validateForm() {
  console.log('🔍 validateForm appelée');
  errors.value = {};
  
  if (!formData.value.bet_date) {
    errors.value.bet_date = 'La date du pari est requise';
  }
  
  // Validation optionnelle des équipes (seulement si les deux sont remplies)
  if (formData.value.team1 && formData.value.team2 && formData.value.team1 === formData.value.team2) {
    errors.value.team1 = 'Les deux équipes doivent être différentes';
    errors.value.team2 = 'Les deux équipes doivent être différentes';
  }
  

  
  if (!formData.value.global_odds || formData.value.global_odds < 1) {
    errors.value.global_odds = 'La cote doit être supérieure ou égale à 1';
  }
  
  if (!formData.value.stake || formData.value.stake <= 0) {
    errors.value.stake = 'La mise doit être supérieure à 0';
  }
  
  const isValid = Object.keys(errors.value).length === 0;
  console.log('📊 Erreurs de validation:', errors.value);
  console.log('✅ Formulaire valide:', isValid);
  return isValid;
}

/**
 * Soumettre le formulaire
 */
async function submitForm() {
  console.log('🔄 submitForm appelée');
  console.log('📋 Données du formulaire:', formData.value);
  console.log('✅ isFormValid:', isFormValid.value);
  
  if (!validateForm()) {
    console.log('❌ Validation échouée');
    return;
  }
  
  console.log('✅ Validation réussie, début de l\'envoi');
  loading.value = true;
  
  try {
    // Préparer les données pour l'API
    const betData = {
      bet_date: formData.value.bet_date.toISOString().split('T')[0], // Format YYYY-MM-DD
      bet_code: events.value.length > 0 ? `Pari combiné (${events.value.length} événements)` : (currentEvent.value.description || formData.value.description || 'Pari libre'),
      global_odds: parseFloat(formData.value.global_odds),
      stake: parseFloat(formData.value.stake),
      stake_type: betTypeValue.value, // Type de mise: 'currency' ou 'percentage'
      result: formData.value.result || 'pending',
      events: eventCards.value.map(eventData => ({
        id: eventData.id,
        sport_id: eventData.sport_id,
        country_id: eventData.country_id,
        league_id: eventData.league,
        team1_id: eventData.team1,
        team2_id: eventData.team2,
        description: eventData.description,
        result: eventData.result,
        odds: eventData.odds
      })) // Array d'événements basé sur eventCards
    };
    
    console.log('📤 Données envoyées à l\'API:', betData);
    
    const response = await BetService.createBet(betData);
    
    console.log('📥 Réponse reçue de l\'API:', response);
    
    if (response.success) {
      toast.add({
        severity: 'success',
        summary: 'Succès',
        detail: 'Pari ajouté avec succès - Données reçues: ' + JSON.stringify(response.data),
        life: 5000
      });
      
      // Émettre l'événement pour informer le parent
      emit('bet-created', response.data);
      
      // Fermer la dialog
      closeDialog();
    } else {
      throw new Error('Erreur lors de la création du pari');
    }
  } catch (error) {
    console.error('❌ Erreur lors de la création du pari:', error);
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de créer le pari: ' + error.message,
      life: 5000
    });
  } finally {
    loading.value = false;
  }
}

/**
 * Fermer la dialog
 */
function closeDialog() {
  emit('closeDialog');
}

/**
 * Réinitialiser le formulaire
 */
function resetForm() {
  formData.value = {
    bet_date: new Date(),
    sport_id: null,
    country_id: null,
    league: null,
    team1: null,
    team2: null,
    global_odds: null,
    stake: null,
    result: 'pending'
  };
  errors.value = {};
  availableLeagues.value = [];
  availableTeams.value = [];
  
  // Réinitialiser les événements et l'événement actuel
  events.value = [];
  currentEvent.value = {
    sport_id: null,
    country_id: null,
    league: null,
    team1: null,
    team2: null,
    bet_code: '',
    description: '',
    result: null,
    odds: null
  };
  
  // Réinitialiser les variables de recherche de pays
  selectedCountry.value = null;
  countrySearchResults.value = [];
  countrySearchQuery.value = '';
  countryCurrentPage.value = 1;
  countryHasMore.value = false;
  countryLoading.value = false;
  
  // Réinitialiser les variables de recherche de ligues
  selectedLeague.value = null;
  leagueSearchResults.value = [];
  leagueSearchQuery.value = '';
  leagueCurrentPage.value = 1;
  leagueHasMore.value = false;
  leagueLoading.value = false;
  
  // Réinitialiser les variables de recherche d'équipes
  selectedTeam1.value = [];
  selectedTeam2.value = [];
  teamSearchResults.value = [];
  teamSearchQuery.value = '';
  teamCurrentPage.value = 1;
  teamHasMore.value = false;
  teamLoading.value = false;
  
  // Nettoyer les event listeners
  cleanupScrollListeners();
}

/**
 * Nettoyer les event listeners de scroll
 */
function cleanupScrollListeners() {
  const panels = document.querySelectorAll('.p-autocomplete-panel .p-autocomplete-items, .p-autocomplete-list-container');
  panels.forEach(panel => {
    if (panel.hasScrollListener) {
      panel.removeEventListener('scroll', handlePanelScroll);
      panel.hasScrollListener = false;
    }
    if (panel.hasTeam1ScrollListener) {
      panel.removeEventListener('scroll', handleTeam1PanelScroll);
      panel.hasTeam1ScrollListener = false;
    }
    if (panel.hasTeam2ScrollListener) {
      panel.removeEventListener('scroll', handleTeam2PanelScroll);
      panel.hasTeam2ScrollListener = false;
    }

  });
}

/**
 * Supprimer la ligue sélectionnée
 */
function clearLeague() {
  selectedLeague.value = null;
  formData.value.league = null;
  // Réinitialiser les équipes quand on supprime la ligue
  selectedTeam1.value = [];
  selectedTeam2.value = [];
  formData.value.team1 = null;
  formData.value.team2 = null;
}

/**
 * Supprimer l'équipe 1 sélectionnée
 */
function clearTeam1() {
  selectedTeam1.value = [];
  formData.value.team1 = null;
}

/**
 * Supprimer l'équipe 2 sélectionnée
 */
function clearTeam2() {
  selectedTeam2.value = [];
  formData.value.team2 = null;
}

/**
 * Ajouter un pari combiné
 */
function addEvent() {
  // Valider que tous les champs requis sont remplis
  if (!formData.value.sport_id || !formData.value.league || !formData.value.team1 || !formData.value.team2 || !currentEvent.value.description) {
    toast.add({
      severity: 'warn',
      summary: 'Champs manquants',
      detail: 'Veuillez remplir tous les champs de l\'événement avant d\'ajouter un nouvel événement.',
      life: 3000
    });
    return;
  }

  // Créer un nouvel événement avec les données actuelles
  const newEvent = {
    id: Date.now(), // ID temporaire
    sport_id: formData.value.sport_id,
    country_id: formData.value.country_id,
    league: selectedLeague.value,
    team1: selectedTeam1.value,
    team2: selectedTeam2.value,
    bet_code: currentEvent.value.description,
    description: currentEvent.value.description,
    result: currentEvent.value.result,
    odds: currentEvent.value.odds
  };

  // Ajouter l'événement à la liste
  events.value.push(newEvent);

  // Réinitialiser les champs pour le prochain événement
  resetEventFields();

  console.log('✅ Événement ajouté:', newEvent);
   console.log('📋 Liste des événements:', events.value);
   
   // Recalculer la cote globale
   calculateGlobalOdds();
 }

/**
 * Ajouter une nouvelle card d'événement
 */
function addEventCard() {
  const newEventCard = {
    id: Date.now(),
    sport_id: null,
    country_id: null,
    league: null,
    team1: null,
    team2: null,
    description: '',
    result: null,
    odds: null,
    selectedSport: [],
    selectedCountry: [],
    selectedLeague: [],
    selectedTeam1: [],
    selectedTeam2: [],
    sportSearchResults: [],
    sportLoading: false,
    countryFilteredResults: [],
    leagueSearchResults: [],
    leagueLoading: false,
    team1SearchResults: [],
    team1Loading: false,
    team2SearchResults: [],
    team2Loading: false
  };
  
  eventCards.value.push(newEventCard);
  console.log('✅ Nouvelle card d\'événement ajoutée:', newEventCard);
}

/**
 * Supprimer une card d'événement
 * @param {number} index - Index de la card à supprimer
 */
function removeEventCard(index) {
  if (eventCards.value.length > 1) {
    eventCards.value.splice(index, 1);
    console.log('🗑️ Card d\'événement supprimée à l\'index:', index);
  }
}
 
 /**
  * Supprimer un événement de la liste
  * @param {number} index - Index de l'événement à supprimer
  */
 function removeEvent(index) {
   events.value.splice(index, 1);
   
   // Recalculer la cote globale après suppression
   calculateGlobalOdds();
   
   console.log('🗑️ Événement supprimé à l\'index:', index);
   console.log('📋 Liste des événements mise à jour:', events.value);
 }

/**
 * Réinitialiser les champs de l'événement actuel
 */
function resetEventFields() {
  // Réinitialiser les champs de l'événement
  formData.value.sport_id = null;
  formData.value.country_id = null;
  formData.value.league = null;
  formData.value.team1 = null;
  formData.value.team2 = null;
  
  // Réinitialiser l'événement actuel
  currentEvent.value.description = '';
  currentEvent.value.result = null;
  currentEvent.value.odds = null;
  
  // Réinitialiser les variables de sélection
  selectedCountry.value = [];
  selectedLeague.value = [];
  selectedTeam1.value = [];
  selectedTeam2.value = [];
  
  // Réinitialiser l'événement actuel
  currentEvent.value = {
    sport_id: null,
    country_id: null,
    league: null,
    team1: null,
    team2: null,
    bet_code: '',
    result: null,
    odds: null
  };
}

/**
 * Calculer la cote globale en multipliant toutes les cotes des événements
 */
function calculateGlobalOdds() {
  if (events.value.length === 0) {
    return;
  }
  
  let globalOdds = 1;
  let hasValidOdds = true;
  
  // Inclure la cote de l'événement actuel s'il y en a une
  if (currentEvent.value.odds && currentEvent.value.odds > 0) {
    globalOdds *= parseFloat(currentEvent.value.odds);
  }
  
  // Multiplier par toutes les cotes des événements ajoutés
  events.value.forEach(event => {
    if (event.odds && event.odds > 0) {
      globalOdds *= parseFloat(event.odds);
    } else {
      hasValidOdds = false;
    }
  });
  
  // Mettre à jour la cote globale seulement si toutes les cotes sont valides
  if (hasValidOdds && globalOdds > 1) {
    formData.value.global_odds = parseFloat(globalOdds.toFixed(2));
  }
}

/**
 * Calculer le résultat global basé sur tous les résultats des événements
 */
function calculateGlobalResult() {
  if (events.value.length === 0) {
    return;
  }
  
  let hasAllResults = true;
  let hasWon = true;
  let hasLost = false;
  let hasVoid = false;
  let hasPending = false;
  
  // Inclure le résultat de l'événement actuel
  const allResults = [...events.value.map(e => e.result)];
  if (currentEvent.value.result) {
    allResults.push(currentEvent.value.result);
  }
  
  allResults.forEach(result => {
    if (!result) {
      hasAllResults = false;
      return;
    }
    
    switch (result) {
      case 'lost':
        hasLost = true;
        hasWon = false;
        break;
      case 'void':
        hasVoid = true;
        break;
      case 'pending':
        hasPending = true;
        hasWon = false;
        break;
      case 'won':
        // Continue à vérifier les autres
        break;
      default:
        hasWon = false;
    }
  });
  
  // Déterminer le résultat global
  if (!hasAllResults || hasPending) {
    formData.value.result = 'pending';
  } else if (hasLost) {
    formData.value.result = 'lost';
  } else if (hasVoid && hasWon) {
    formData.value.result = 'won'; // Si certains sont void mais les autres gagnés
  } else if (hasVoid) {
    formData.value.result = 'void';
  } else if (hasWon) {
    formData.value.result = 'won';
  }
}

/**
 * Récupérer le capital actuel de l'utilisateur
 */
async function fetchCurrentCapital() {
  try {
    capitalLoading.value = true;
    const response = await BetService.getCapitalEvolution();
    
    if (response.success && response.data) {
      currentCapital.value = response.current_capital || response.initial_capital || 0;
    }
  } catch (error) {
    console.error('Erreur lors de la récupération du capital actuel:', error);
    currentCapital.value = 0;
  } finally {
    capitalLoading.value = false;
  }
}

/**
 * Calculer la mise en pourcentage du capital
 */
function calculatePercentageStake() {
  if (betTypeValue.value === 'percentage' && formData.value.stake && currentCapital.value > 0) {
    const percentage = parseFloat(formData.value.stake);
    if (!isNaN(percentage) && percentage > 0) {
      calculatedStake.value = (currentCapital.value * percentage) / 100;
      return;
    }
  }
  calculatedStake.value = 0;
}

// Watchers
// Surveiller le changement de type de mise pour récupérer le capital
watch(betTypeValue, async (newValue) => {
  if (newValue === 'percentage') {
    await fetchCurrentCapital();
  }
  calculatePercentageStake();
});

// Surveiller les changements de la mise pour recalculer en mode pourcentage
watch(() => formData.value.stake, () => {
  calculatePercentageStake();
});

// Surveiller les changements dans les résultats des événements
watch(
  () => [events.value.map(e => e.result), currentEvent.value.result],
  () => {
    calculateGlobalResult();
  },
  { deep: true }
);

// Lifecycle
onMounted(async () => {
  await loadSports();
  await loadCountries();
});
</script>

<style scoped>
.p-invalid {
  border-color: #ef4444;
}



/* Styles personnalisés pour les composants Select */
:deep(.select-custom .p-dropdown-panel) {
  max-width: 100% !important;
  width: auto !important;
}

:deep(.select-panel-custom) {
  max-width: calc(50vw - 4rem) !important;
  width: auto !important;
  max-height: 200px !important;
  overflow-y: auto !important;
  z-index: 9999 !important;
  position: absolute !important;
}

@media (max-width: 960px) {
  :deep(.select-panel-custom) {
    max-width: calc(90vw - 4rem) !important;
  }
}

:deep(.select-panel-custom .p-dropdown-items) {
  max-width: 100% !important;
  max-height: 180px !important;
  overflow-y: auto !important;
}

:deep(.select-panel-custom .p-dropdown-item) {
  max-width: 100% !important;
  overflow: hidden !important;
  text-overflow: ellipsis !important;
  white-space: nowrap !important;
}

:deep(.select-custom .p-dropdown-label) {
  max-width: 100% !important;
  overflow: hidden !important;
  text-overflow: ellipsis !important;
  white-space: nowrap !important;
}

/* Contraindre la modal et ses éléments */
:deep(.p-dialog) {
  overflow: visible !important;
}

:deep(.p-dialog-content) {
  overflow: visible !important;
}

</style>

<style>
/* Contraindre l'overlay du sélecteur */
.p-select-overlay {
  max-width: calc(50vw - 4rem) !important;
  width: auto !important;
}

@media (max-width: 960px) {
  .p-select-overlay {
    max-width: calc(90vw - 4rem) !important;
  }
}
</style>

